<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\CanonicalJournalStore;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\JournalRecord;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlCanonicalJournalStore implements CanonicalJournalStore
{
    private PostgreSqlJournalSchema $schema;
    private PostgreSqlJournalRecordCodec $codec;

    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
        string $schema = 'blackops',
        ?PostgreSqlJournalRecordCodec $codec = null,
    ) {
        $this->schema = new PostgreSqlJournalSchema($schema);
        $this->codec = $codec ?? new PostgreSqlJournalRecordCodec();
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new JournalWriteFailed('Failed to migrate PostgreSQL journal schema.', previous: $exception);
        }
    }

    public function append(JournalRecord $record): void
    {
        $table = $this->schema->journalTable();
        $operationsTable = $this->schema->operationsTable();
        $operationsGuard = '';
        $operationsRegclass = $this->connection->fetchOne('SELECT to_regclass(:table)', ['table' => $operationsTable]);
        if (is_string($operationsRegclass) && $operationsRegclass !== '') {
            $operationsGuard = "
        AND NOT EXISTS (
            SELECT 1 FROM {$operationsTable} operation
            WHERE operation.operation_id = :operation_id
              AND (
                operation.operation_type IS DISTINCT FROM :operation_type
                OR operation.tenant_type IS DISTINCT FROM :tenant_type
                OR operation.tenant_id IS DISTINCT FROM :tenant_id
                OR operation.origin_actor_type IS DISTINCT FROM :origin_actor_type
                OR operation.origin_actor_id IS DISTINCT FROM :origin_actor_id
              )
        )";
        }
        $sql = "INSERT INTO {$table} (
            record_id,
            operation_id,
            operation_type,
            sequence,
            event,
            attempt_id,
            schema_version,
            operation_schema_version,
            occurred_at,
            tenant_type,
            tenant_id,
            origin_actor_type,
            origin_actor_id,
            encoded_record
        ) SELECT
            :record_id,
            :operation_id,
            :operation_type,
            :sequence,
            :event,
            :attempt_id,
            :schema_version,
            :operation_schema_version,
            :occurred_at,
            :tenant_type,
            :tenant_id,
            :origin_actor_type,
            :origin_actor_id,
            :encoded_record
        WHERE NOT EXISTS (
            SELECT 1 FROM {$table} existing
            WHERE existing.operation_id = :operation_id
              AND (
                existing.operation_type IS DISTINCT FROM :operation_type
                OR existing.tenant_type IS DISTINCT FROM :tenant_type
                OR existing.tenant_id IS DISTINCT FROM :tenant_id
                OR existing.origin_actor_type IS DISTINCT FROM :origin_actor_type
                OR existing.origin_actor_id IS DISTINCT FROM :origin_actor_id
              )
        )
        {$operationsGuard}";

        try {
            $inserted = $this->connection->executeStatement(
                $sql,
                [
                    'record_id' => $record->recordId->toString(),
                    'operation_id' => $record->operation->id->toString(),
                    'operation_type' => $record->operation->type,
                    'sequence' => $record->sequence,
                    'event' => $record->event->value,
                    'attempt_id' => $record->attempt?->id->toString(),
                    'schema_version' => $record->schemaVersion,
                    'operation_schema_version' => $record->operation->schemaVersion,
                    'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'tenant_type' => $record->operation->tenant?->type(),
                    'tenant_id' => $record->operation->tenant?->id(),
                    'origin_actor_type' => $record->operation->actorContext?->origin()?->type(),
                    'origin_actor_id' => $record->operation->actorContext?->origin()?->id(),
                    'encoded_record' => $this->encode($record),
                ],
                ['encoded_record' => ParameterType::BINARY],
            );
            if ((int) $inserted !== 1) {
                throw new JournalWriteFailed('PostgreSQL journal operation tenant subject is inconsistent.');
            }
        } catch (Throwable $exception) {
            throw new JournalWriteFailed('Failed to append PostgreSQL journal record.', previous: $exception);
        }
    }

    public function records(OperationId $operationId): iterable
    {
        $table = $this->schema->journalTable();
        $sql = "SELECT record_id::text AS record_id, operation_id::text AS operation_id,
                    operation_type, schema_version, operation_schema_version, tenant_type, tenant_id,
                    origin_actor_type, origin_actor_id, encoded_record
            FROM {$table}
            WHERE operation_id = :operation_id
            ORDER BY sequence ASC";

        try {
            $rows = $this->connection->iterateAssociative($sql, [
                'operation_id' => $operationId->toString(),
            ]);

            foreach ($rows as $row) {
                /** @var mixed $payload */
                $payload = $row['encoded_record'] ?? null;

                yield $this->decode($row);
            }
        } catch (Throwable $exception) {
            throw new JournalReadFailed('Failed to read PostgreSQL journal records.', previous: $exception);
        }
    }

    /** @return iterable<\BlackOps\Journal\JournalRecord> */
    public function recordsForTenant(OperationId $operationId, ?TenantRef $tenant): iterable
    {
        $table = $this->schema->journalTable();
        $sql = "SELECT record_id::text AS record_id, operation_id::text AS operation_id,
                    operation_type, schema_version, operation_schema_version, tenant_type, tenant_id,
                    origin_actor_type, origin_actor_id, encoded_record
            FROM {$table}
            WHERE operation_id = :operation_id
              AND tenant_type IS NOT DISTINCT FROM :tenant_type
              AND tenant_id IS NOT DISTINCT FROM :tenant_id
            ORDER BY sequence ASC";
        try {
            foreach ($this->connection->iterateAssociative($sql, [
                'operation_id' => $operationId->toString(),
                'tenant_type' => $tenant?->type(),
                'tenant_id' => $tenant?->id(),
            ]) as $row) {
                yield $this->decode($row);
            }
        } catch (Throwable $exception) {
            throw new JournalReadFailed('Failed to read PostgreSQL tenant journal records.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): JournalRecord
    {
        $encoded = PostgreSqlBytea::string($row['encoded_record'] ?? null);
        $tenant = $this->tenant($row);
        $context = new StorageProtectionContext(
            \BlackOps\StorageProtection\StoragePurpose::JournalRecord,
            (string) $row['record_id'],
            (string) $row['operation_id'],
            (string) $row['operation_type'],
            (int) $row['operation_schema_version'],
            $tenant,
        );
        $encoded = $this->protection->decrypt($encoded, $context);
        $record = $this->codec->decode($encoded);
        if (
            (string) $row['record_id'] !== $record->recordId->toString()
            || (string) $row['operation_id'] !== $record->operation->id->toString()
            || (string) $row['operation_type'] !== $record->operation->type
            || (int) $row['schema_version'] !== $record->schemaVersion
            || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
            || ($row['tenant_type'] ?? null) !== $record->operation->tenant?->type()
            || ($row['tenant_id'] ?? null) !== $record->operation->tenant?->id()
            || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
            || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
        ) {
            throw new JournalReadFailed('Canonical journal clear metadata is inconsistent.');
        }
        return $record;
    }

    /** @param array<string, mixed> $row */
    private function tenant(array $row): ?TenantRef
    {
        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw new JournalReadFailed('Canonical journal tenant subject is invalid.');
        }
        return new TenantRef($type, $id);
    }

    private function encode(JournalRecord $record): string
    {
        $encoded = $this->codec->encode($record);
        return $this->protection->encrypt(
            $encoded,
            new StorageProtectionContext(
                \BlackOps\StorageProtection\StoragePurpose::JournalRecord,
                $record->recordId->toString(),
                $record->operation->id->toString(),
                $record->operation->type,
                $record->operation->schemaVersion,
                $record->operation->tenant,
            ),
        );
    }
}
