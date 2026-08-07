<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Journal\CanonicalJournalStore;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\JournalRecord;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlCanonicalJournalStore implements CanonicalJournalStore
{
    private PostgreSqlJournalSchema $schema;
    private PostgreSqlJournalRecordCodec $codec;

    public function __construct(
        private Connection $connection,
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
            :occurred_at,
            :tenant_type,
            :tenant_id,
            :origin_actor_type,
            :origin_actor_id,
            convert_to(:encoded_record, 'UTF8')
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
            $inserted = $this->connection->executeStatement($sql, [
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operation->id->toString(),
                'operation_type' => $record->operation->type,
                'sequence' => $record->sequence,
                'event' => $record->event->value,
                'attempt_id' => $record->attempt?->id->toString(),
                'schema_version' => $record->schemaVersion,
                'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'tenant_type' => $record->operation->tenant?->type(),
                'tenant_id' => $record->operation->tenant?->id(),
                'origin_actor_type' => $record->operation->actorContext?->origin()?->type(),
                'origin_actor_id' => $record->operation->actorContext?->origin()?->id(),
                'encoded_record' => $this->codec->encode($record),
            ]);
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
        $sql = "SELECT encoded_record
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

                yield $this->codec->decode(PostgreSqlBytea::string($payload));
            }
        } catch (Throwable $exception) {
            throw new JournalReadFailed('Failed to read PostgreSQL journal records.', previous: $exception);
        }
    }

    /** @return iterable<\BlackOps\Journal\JournalRecord> */
    public function recordsForTenant(OperationId $operationId, ?TenantRef $tenant): iterable
    {
        $table = $this->schema->journalTable();
        $sql = "SELECT encoded_record
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
                yield $this->codec->decode(PostgreSqlBytea::string($row['encoded_record'] ?? null));
            }
        } catch (Throwable $exception) {
            throw new JournalReadFailed('Failed to read PostgreSQL tenant journal records.', previous: $exception);
        }
    }
}
