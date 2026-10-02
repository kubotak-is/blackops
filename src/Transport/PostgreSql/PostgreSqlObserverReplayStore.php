<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\JournalRecord;
use BlackOps\StorageProtection\StoragePurpose;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * PostgreSQL adapter for bounded canonical replay and its durable cursor.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class PostgreSqlObserverReplayStore
{
    private PostgreSqlJournalSchema $schema;
    private PostgreSqlJournalRecordCodec $codec;
    private PostgreSqlObserverReplayCheckpointWriter $checkpointWriter;
    private PostgreSqlObserverReplayAuditWriter $auditWriter;

    public function __construct(
        #[\SensitiveParameter]
        private Connection $connection,
        #[\SensitiveParameter]
        private BopdEnvelopeCodec $protection,
        #[\SensitiveParameter]
        string $schema = 'blackops',
        #[\SensitiveParameter]
        ?PostgreSqlJournalRecordCodec $codec = null,
        #[\SensitiveParameter]
        private ?PostgreSqlObserverReplayPhaseHook $hook = null,
    ) {
        $this->schema = new PostgreSqlJournalSchema($schema);
        $this->codec = $codec ?? new PostgreSqlJournalRecordCodec();
        $this->checkpointWriter = new PostgreSqlObserverReplayCheckpointWriter($connection);
        $this->auditWriter = new PostgreSqlObserverReplayAuditWriter(
            $connection,
            $this->schema->observerReplayAuditTable(),
        );
    }

    /**
     * @return array{records: list<JournalRecord>, hasMore: bool}
     * @mago-expect lint:halstead
     */
    public function select(PostgreSqlObserverReplaySelector $selector, int $limit, ?string $cursor): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Replay batch size must be between 1 and 1000.');
        }
        $query = PostgreSqlObserverReplaySelectionQuery::build(
            $selector,
            $limit,
            $cursor,
            $this->schema->journalTable(),
        );
        try {
            $records = [];
            /** @var array<string, mixed>|list<mixed> $params */
            $params = $query['params'];
            foreach ($this->connection->iterateAssociative($query['sql'], $params) as $row) {
                $encoded = PostgreSqlBytea::string($row['encoded_record'] ?? null);
                $tenantType = $row['tenant_type'] ?? null;
                $tenantId = $row['tenant_id'] ?? null;
                $tenant = null;
                if ($tenantType !== null || $tenantId !== null) {
                    if (!is_string($tenantType) || $tenantType === '' || !is_string($tenantId) || $tenantId === '') {
                        throw new RuntimeException('Canonical journal tenant subject is invalid.');
                    }
                    $tenant = new TenantRef($tenantType, $tenantId);
                }
                $encoded = $this->protection->decrypt(
                    $encoded,
                    new StorageProtectionContext(
                        StoragePurpose::JournalRecord,
                        (string) $row['record_id'],
                        (string) $row['operation_id'],
                        (string) $row['operation_type'],
                        (int) $row['operation_schema_version'],
                        $tenant,
                    ),
                );
                $record = $this->codec->decode($encoded);
                $tenant = $record->operation->tenant;
                if (
                    ($row['tenant_type'] ?? null) !== $tenant?->type()
                    || ($row['tenant_id'] ?? null) !== $tenant?->id()
                ) {
                    throw new RuntimeException('Canonical journal tenant subject is inconsistent.');
                }
                if (
                    (string) $row['record_id'] !== $record->recordId->toString()
                    || (string) $row['operation_id'] !== $record->operation->id->toString()
                    || (string) $row['operation_type'] !== $record->operation->type
                    || (int) $row['schema_version'] !== $record->schemaVersion
                    || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                    || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                    || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                ) {
                    throw new RuntimeException('Canonical journal clear metadata is inconsistent.');
                }
                $records[] = $record;
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Canonical journal replay selection failed.', previous: $exception);
        }
        $hasMore = count($records) > $limit;
        if ($hasMore) {
            array_pop($records);
        }
        return ['records' => $records, 'hasMore' => $hasMore];
    }

    public function begin(
        #[\SensitiveParameter]
        PostgreSqlObserverReplayBeginRequest $request,
    ): PostgreSqlObserverReplayBinding {
        return PostgreSqlObserverReplayBeginRoot::run([
            'connection' => $this->connection,
            'schema' => $this->schema,
            'checkpointWriter' => $this->checkpointWriter,
            'auditWriter' => $this->auditWriter,
            'hook' => $this->hook,
            'request' => $request,
        ]);
    }

    public function unlock(string $checkpoint): void
    {
        $unlocked = $this->connection->fetchOne('SELECT pg_advisory_unlock(hashtext(:checkpoint))', [
            'checkpoint' => $checkpoint,
        ]);
        if ($unlocked !== true && $unlocked !== 't' && $unlocked !== 1 && $unlocked !== '1') {
            throw new RuntimeException('Replay checkpoint advisory lock was not owned.');
        }
    }

    public function finishInvocation(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $state,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now = null,
        #[\SensitiveParameter]
        ?string $auditId = null,
    ): void {
        if (!$this->connection->isAutoCommit() || $this->connection->getTransactionNestingLevel() !== 0) {
            throw new RuntimeException('Replay mutation requires an idle auto-commit connection.');
        }
        if ($auditId === null) {
            throw new InvalidArgumentException('Replay completion audit ID is required.');
        }
        $this->hook?->assertEntry($this->connection, $checkpoint);
        $now ??= new DateTimeImmutable('now');
        $table = $this->schema->observerReplayCheckpointTable();
        $audit = $this->schema->observerReplayAuditTable();
        try {
            $this->connection->beginTransaction();
            $affected = $this->connection->executeStatement(
                "UPDATE {$table} SET state=:state, updated_at=:at WHERE checkpoint_id=:id AND state='running'",
                [
                    'state' => $state,
                    'at' => $now->format('Y-m-d H:i:s.uP'),
                    'id' => $checkpoint,
                ],
            );
            if ($affected !== 1) {
                throw new RuntimeException('Replay checkpoint ownership was lost.');
            }
            $auditAffected = $this->connection->executeStatement(
                "UPDATE {$audit} SET state='complete', finished_at=:at WHERE audit_id=:audit AND checkpoint_id=:id AND state='started'",
                ['at' => $now->format('Y-m-d H:i:s.uP'), 'audit' => $auditId, 'id' => $checkpoint],
            );
            if ($auditAffected !== 1) {
                throw new RuntimeException('Replay audit ownership was lost.');
            }
            $this->hook?->finished($this->connection, $checkpoint, $auditId, $state);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable $rollbackException) {
                    $exception = new RuntimeException(
                        'Replay completion rollback failed.',
                        previous: $rollbackException,
                    );
                }
            }
            throw $exception;
        }
    }

    public function load(string $checkpoint): PostgreSqlObserverReplayLoaded
    {
        $table = $this->schema->observerReplayCheckpointTable();
        $row = $this->connection->fetchAssociative(
            "SELECT selector_kind, selector_operation_id, selector_record_id, selector_from, selector_to, target_names, cursor_record_id FROM {$table} WHERE checkpoint_id=:id",
            ['id' => $checkpoint],
        );
        if ($row === false) {
            throw new InvalidArgumentException('Replay checkpoint does not exist.');
        }
        $selector = match ($row['selector_kind']) {
            'operation' => PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
                (string) $row['selector_operation_id'],
            )),
            'record' => PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(
                (string) $row['selector_record_id'],
            )),
            default => PostgreSqlObserverReplaySelector::time(
                new DateTimeImmutable((string) $row['selector_from']),
                new DateTimeImmutable((string) $row['selector_to']),
            ),
        };
        $targets = PostgreSqlObserverReplayIdentity::decodeTargets($row['target_names'] ?? null);
        return new PostgreSqlObserverReplayLoaded(
            $selector,
            $targets,
            is_string($row['cursor_record_id'] ?? null) ? $row['cursor_record_id'] : null,
        );
    }

    public function advance(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        ?string $cursor,
        #[\SensitiveParameter]
        int $delivered,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now = null,
    ): void {
        PostgreSqlObserverReplayAdvanceRoot::run([
            'connection' => $this->connection,
            'schema' => $this->schema,
            'hook' => $this->hook,
            'checkpoint' => $checkpoint,
            'cursor' => $cursor,
            'delivered' => $delivered,
            'auditId' => $auditId,
            'now' => $now,
            'source' => null,
        ]);
    }

    public function advanceSelected(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        ?string $cursor,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        JournalRecord $source,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now = null,
    ): void {
        PostgreSqlObserverReplayAdvanceRoot::run([
            'connection' => $this->connection,
            'schema' => $this->schema,
            'hook' => $this->hook,
            'checkpoint' => $checkpoint,
            'cursor' => $cursor,
            'delivered' => 1,
            'auditId' => $auditId,
            'now' => $now,
            'source' => $source,
        ]);
    }

    public function cursorFor(PostgreSqlObserverReplaySelector $selector, JournalRecord $record): string
    {
        return match ($selector->kind) {
            PostgreSqlObserverReplaySelector::OPERATION => $record->sequence . '|' . $record->recordId->toString(),
            PostgreSqlObserverReplaySelector::TIME => $record->occurredAt->format('Y-m-d H:i:s.uP')
                . '|'
                . $record->recordId->toString(),
            PostgreSqlObserverReplaySelector::RECORD => $record->recordId->toString(),
            default => throw new InvalidArgumentException('Replay selector kind is invalid.'),
        };
    }

    public function fail(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        Throwable $exception,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now = null,
        #[\SensitiveParameter]
        ?string $auditId = null,
    ): void {
        if (!$this->connection->isAutoCommit() || $this->connection->getTransactionNestingLevel() !== 0) {
            throw new RuntimeException('Replay mutation requires an idle auto-commit connection.');
        }
        if ($auditId === null) {
            throw new InvalidArgumentException('Replay failure audit ID is required.');
        }
        $this->hook?->assertEntry($this->connection, $checkpoint);
        $now ??= new DateTimeImmutable('now');
        $table = $this->schema->observerReplayCheckpointTable();
        $audit = $this->schema->observerReplayAuditTable();
        $fingerprint = 'v1:' . hash('sha256', "blackops.observer.replay.failure.v1\0" . $exception::class);
        try {
            $this->connection->beginTransaction();
            $checkpointAffected = $this->connection->executeStatement(
                "UPDATE {$table} SET state='failed', failure_fingerprint=:fingerprint, updated_at=:at WHERE checkpoint_id=:id AND state='running'",
                ['fingerprint' => $fingerprint, 'at' => $now->format('Y-m-d H:i:s.uP'), 'id' => $checkpoint],
            );
            if ($checkpointAffected !== 1) {
                throw new RuntimeException('Replay checkpoint ownership was lost.');
            }
            $auditAffected = $this->connection->executeStatement(
                "UPDATE {$audit} SET state='failed', failure_fingerprint=:fingerprint, finished_at=:at WHERE audit_id=:audit AND checkpoint_id=:id AND state='started'",
                [
                    'fingerprint' => $fingerprint,
                    'at' => $now->format('Y-m-d H:i:s.uP'),
                    'audit' => $auditId,
                    'id' => $checkpoint,
                ],
            );
            if ($auditAffected !== 1) {
                throw new RuntimeException('Replay audit ownership was lost.');
            }
            $this->hook?->failed($this->connection, $checkpoint, $auditId);
            $this->connection->commit();
        } catch (Throwable $transactionException) {
            if ($this->connection->isTransactionActive()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable $rollbackException) {
                    $transactionException = new RuntimeException(
                        'Replay failure rollback failed.',
                        previous: $rollbackException,
                    );
                }
            }
            throw $transactionException;
        }
    }
}
