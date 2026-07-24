<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\JournalRecord;
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
        private Connection $connection,
        string $schema = 'blackops',
        ?PostgreSqlJournalRecordCodec $codec = null,
    ) {
        $this->schema = new PostgreSqlJournalSchema($schema);
        $this->codec = $codec ?? new PostgreSqlJournalRecordCodec();
        $this->checkpointWriter = new PostgreSqlObserverReplayCheckpointWriter($connection);
        $this->auditWriter = new PostgreSqlObserverReplayAuditWriter(
            $connection,
            $this->schema->observerReplayAuditTable(),
        );
    }

    /** @return array{records: list<JournalRecord>, hasMore: bool} */
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
                if (!is_string($row['encoded_record'] ?? null)) {
                    throw new RuntimeException('Canonical journal row is invalid.');
                }
                $records[] = $this->codec->decode($row['encoded_record']);
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

    public function begin(PostgreSqlObserverReplayBeginRequest $request): PostgreSqlObserverReplayBinding
    {
        PostgreSqlObserverReplayIdentity::assertCheckpoint($request->checkpoint);
        $now = $request->now ?? new DateTimeImmutable('now');
        $selectorHash = hash('sha256', PostgreSqlObserverReplayIdentity::selectorKey($request->selector));
        $targets = $request->targets;
        sort($targets);
        $targetHash = hash('sha256', json_encode(array_values($targets), JSON_THROW_ON_ERROR));
        $table = $this->schema->observerReplayCheckpointTable();
        $audit = $this->schema->observerReplayAuditTable();
        $locked = $this->connection->fetchOne('SELECT pg_try_advisory_lock(hashtext(:checkpoint))', [
            'checkpoint' => $request->checkpoint,
        ]);
        if ($locked !== true && $locked !== 't' && $locked !== 1 && $locked !== '1') {
            throw new InvalidArgumentException('Replay checkpoint is already running.');
        }
        $this->connection->beginTransaction();
        try {
            $row = $this->checkpointWriter->row($table, $request->checkpoint);
            if ($row !== false && ($row['selector_hash'] !== $selectorHash || $row['target_hash'] !== $targetHash)) {
                throw new InvalidArgumentException('Replay checkpoint is bound to a different selector or target set.');
            }
            $auditId = bin2hex(random_bytes(16));
            if ($row === false) {
                $this->checkpointWriter->insert($table, $request, $selectorHash, $targetHash, $now);
            }
            if ($row !== false && $row['state'] !== 'running') {
                $this->checkpointWriter->resume($table, $request->checkpoint, $now);
            }
            $this->auditWriter->insert($request, $selectorHash, $targetHash, $auditId, $now);
            $this->connection->commit();
            return new PostgreSqlObserverReplayBinding(
                is_string($row['cursor_record_id'] ?? null) ? $row['cursor_record_id'] : null,
                $auditId,
            );
        } catch (Throwable $exception) {
            try {
                $this->connection->rollBack();
            } catch (Throwable $rollbackException) {
                $exception = new RuntimeException('Replay transaction rollback failed.', previous: $rollbackException);
            }
            try {
                $this->unlock($request->checkpoint);
            } catch (Throwable $unlockException) {
                $exception = new RuntimeException('Replay checkpoint unlock failed.', previous: $unlockException);
            }
            throw $exception;
        }
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
        string $checkpoint,
        string $state,
        ?DateTimeImmutable $now = null,
        ?string $auditId = null,
    ): void {
        if ($auditId === null) {
            throw new InvalidArgumentException('Replay completion audit ID is required.');
        }
        $now ??= new DateTimeImmutable('now');
        $table = $this->schema->observerReplayCheckpointTable();
        $audit = $this->schema->observerReplayAuditTable();
        $this->connection->beginTransaction();
        try {
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
            $this->connection->commit();
        } catch (Throwable $exception) {
            try {
                $this->connection->rollBack();
            } catch (Throwable $rollbackException) {
                $exception = new RuntimeException('Replay completion rollback failed.', previous: $rollbackException);
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
        string $checkpoint,
        ?string $cursor,
        int $delivered,
        string $auditId,
        ?DateTimeImmutable $now = null,
    ): void {
        $now ??= new DateTimeImmutable('now');
        $table = $this->schema->observerReplayCheckpointTable();
        $recordId = PostgreSqlObserverReplayIdentity::recordIdFromCursor($cursor);
        $this->connection->beginTransaction();
        try {
            $affected = $this->connection->executeStatement(
                "UPDATE {$table} SET cursor_record_id=:cursor, selected_count=selected_count+:count, delivered_count=delivered_count+:count, first_record_id=COALESCE(first_record_id, :record_id), last_record_id=:record_id, state=:state, updated_at=:at WHERE checkpoint_id=:id AND state='running'",
                [
                    'cursor' => $cursor,
                    'count' => $delivered,
                    'record_id' => $recordId,
                    'state' => 'running',
                    'at' => $now->format('Y-m-d H:i:s.uP'),
                    'id' => $checkpoint,
                ],
            );
            if ($affected !== 1) {
                throw new RuntimeException('Replay checkpoint ownership was lost.');
            }
            $auditAffected = $this->connection->executeStatement(
                "UPDATE {$this->schema->observerReplayAuditTable()} SET selected_count=selected_count+:count, delivered_count=delivered_count+:count, first_record_id=COALESCE(first_record_id, :record_id), last_record_id=:record_id WHERE audit_id=:audit AND checkpoint_id=:id AND state='started'",
                ['count' => $delivered, 'record_id' => $recordId, 'audit' => $auditId, 'id' => $checkpoint],
            );
            if ($auditAffected !== 1) {
                throw new RuntimeException('Replay audit ownership was lost.');
            }
            $this->connection->commit();
        } catch (Throwable $exception) {
            try {
                $this->connection->rollBack();
            } catch (Throwable $rollbackException) {
                $exception = new RuntimeException('Replay advance rollback failed.', previous: $rollbackException);
            }
            throw $exception;
        }
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
        string $checkpoint,
        Throwable $exception,
        ?DateTimeImmutable $now = null,
        ?string $auditId = null,
    ): void {
        if ($auditId === null) {
            throw new InvalidArgumentException('Replay failure audit ID is required.');
        }
        $now ??= new DateTimeImmutable('now');
        $table = $this->schema->observerReplayCheckpointTable();
        $audit = $this->schema->observerReplayAuditTable();
        $fingerprint = 'v1:' . hash('sha256', "blackops.observer.replay.failure.v1\0" . $exception::class);
        $this->connection->beginTransaction();
        try {
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
            $this->connection->commit();
        } catch (Throwable $transactionException) {
            try {
                $this->connection->rollBack();
            } catch (Throwable $rollbackException) {
                $transactionException = new RuntimeException(
                    'Replay failure rollback failed.',
                    previous: $rollbackException,
                );
            }
            throw $transactionException;
        }
    }
}
