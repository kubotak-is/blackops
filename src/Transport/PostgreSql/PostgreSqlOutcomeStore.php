<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Outcome\OutcomeStore;
use BlackOps\StorageProtection\StoragePurpose;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:too-many-methods */
final readonly class PostgreSqlOutcomeStore implements OutcomeStore
{
    private PostgreSqlOutcomeSchema $schema;

    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
        string $schema = 'blackops',
        private PostgreSqlOutcomeCodec $codec = new PostgreSqlOutcomeCodec(),
    ) {
        $this->schema = new PostgreSqlOutcomeSchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new OutcomeStoreException('Failed to migrate PostgreSQL outcome schema.', previous: $exception);
        }
    }

    public function save(OutcomeRecord $record): void
    {
        $encoded = $this->codec->encode($record->outcome());
        $table = $this->schema->table();

        try {
            $operation = $this->connection->fetchAssociative(
                "SELECT operation_type, schema_version, tenant_type, tenant_id
                 FROM {$this->schema->operationsTable()}
                 WHERE operation_id = :operation_id AND state = 'completed'",
                ['operation_id' => $record->operationId()->toString()],
            );
            if ($operation === false) {
                throw new OutcomeStoreException('PostgreSQL outcome requires a completed operation.');
            }
            $payload = $encoded->payload;
            $payload = $this->protection->encrypt($payload, $this->context(
                $record->operationId(),
                (string) $operation['operation_type'],
                (int) $operation['schema_version'],
                $this->tenant($operation),
            ));
            $inserted = $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    operation_id,
                    outcome_type,
                    schema_version,
                    tenant_type,
                    tenant_id,
                    encoded_payload,
                    completed_at
                ) SELECT
                    :operation_id,
                    :outcome_type,
                    :schema_version,
                    o.tenant_type,
                    o.tenant_id,
                    :encoded_payload,
                    :completed_at
                FROM {$this->schema->operationsTable()} o
                WHERE o.operation_id = :operation_id
                    AND o.state = 'completed'",
                [
                    'operation_id' => $record->operationId()->toString(),
                    'outcome_type' => $encoded->type,
                    'schema_version' => $encoded->schemaVersion,
                    'encoded_payload' => $payload,
                    'completed_at' => $record->completedAt()->format('Y-m-d H:i:s.uP'),
                ],
                ['encoded_payload' => ParameterType::BINARY],
            );

            if ((int) $inserted !== 1) {
                throw new OutcomeStoreException('PostgreSQL outcome requires a completed operation.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof OutcomeStoreException) {
                throw $exception;
            }

            throw new OutcomeStoreException('Failed to save PostgreSQL outcome.', previous: $exception);
        }
    }

    public function find(OperationId $operationId): ?OutcomeRecord
    {
        $table = $this->schema->table();

        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    outcome.outcome_type,
                    outcome.schema_version,
                    o.operation_type,
                    o.schema_version AS operation_schema_version,
                    outcome.tenant_type AS outcome_tenant_type,
                    outcome.tenant_id AS outcome_tenant_id,
                    o.tenant_type AS operation_tenant_type,
                    o.tenant_id AS operation_tenant_id,
                    outcome.encoded_payload,
                    outcome.completed_at::text AS completed_at
                FROM {$table} outcome JOIN {$this->schema->operationsTable()} o USING (operation_id)
                WHERE outcome.operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );

            if ($row === false) {
                return null;
            }

            $payload = $this->payload($row, $operationId);
            return new OutcomeRecord(
                $operationId,
                $this->codec->decode(
                    $this->string($row, 'outcome_type'),
                    $this->integer($row, 'schema_version'),
                    $payload,
                ),
                new DateTimeImmutable($this->string($row, 'completed_at')),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof OutcomeStoreException) {
                throw $exception;
            }

            throw new OutcomeStoreException('Failed to find PostgreSQL outcome.', previous: $exception);
        }
    }

    public function findForTenant(OperationId $operationId, ?TenantRef $tenant): ?OutcomeRecord
    {
        $table = $this->schema->table();
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT outcome.outcome_type, outcome.schema_version,
                    o.operation_type,
                    o.schema_version AS operation_schema_version,
                    outcome.tenant_type AS outcome_tenant_type,
                    outcome.tenant_id AS outcome_tenant_id,
                    o.tenant_type AS operation_tenant_type,
                    o.tenant_id AS operation_tenant_id,
                    outcome.encoded_payload,
                    outcome.completed_at::text AS completed_at
                 FROM {$table} outcome JOIN {$this->schema->operationsTable()} o USING (operation_id)
                 WHERE outcome.operation_id = :operation_id
                   AND outcome.tenant_type IS NOT DISTINCT FROM :tenant_type
                   AND outcome.tenant_id IS NOT DISTINCT FROM :tenant_id
                   AND o.tenant_type IS NOT DISTINCT FROM :tenant_type
                   AND o.tenant_id IS NOT DISTINCT FROM :tenant_id",
                [
                    'operation_id' => $operationId->toString(),
                    'tenant_type' => $tenant?->type(),
                    'tenant_id' => $tenant?->id(),
                ],
            );
            if ($row === false) {
                return null;
            }
            $payload = $this->payload($row, $operationId);
            return new OutcomeRecord(
                $operationId,
                $this->codec->decode(
                    $this->string($row, 'outcome_type'),
                    $this->integer($row, 'schema_version'),
                    $payload,
                ),
                new DateTimeImmutable($this->string($row, 'completed_at')),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof OutcomeStoreException) {
                throw $exception;
            }
            throw new OutcomeStoreException('Failed to find PostgreSQL tenant outcome.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new OutcomeStoreException('PostgreSQL outcome row contains an invalid string field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new OutcomeStoreException('PostgreSQL outcome row contains an invalid integer field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function payload(array $row, OperationId $operationId): string
    {
        $payload = PostgreSqlBytea::string($row['encoded_payload'] ?? null);
        return $this->protection->decrypt($payload, $this->context(
            $operationId,
            $this->string($row, 'operation_type'),
            $this->integer($row, 'operation_schema_version'),
            $this->rowTenant($row, 'outcome_tenant_type', 'outcome_tenant_id'),
        ));
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
            throw new OutcomeStoreException('PostgreSQL outcome tenant subject is invalid.');
        }
        return new TenantRef($type, $id);
    }

    /** @param array<string, mixed> $row */
    private function rowTenant(array $row, string $typeKey, string $idKey): ?TenantRef
    {
        $type = $row[$typeKey] ?? null;
        $id = $row[$idKey] ?? null;
        $operationType = $row['operation_tenant_type'] ?? null;
        $operationId = $row['operation_tenant_id'] ?? null;
        if ($type === null && $id === null) {
            if ($operationType !== null || $operationId !== null) {
                throw new OutcomeStoreException('PostgreSQL outcome clear tenant metadata is inconsistent.');
            }
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw new OutcomeStoreException('PostgreSQL outcome tenant subject is invalid.');
        }
        if ($operationType !== $type || $operationId !== $id) {
            throw new OutcomeStoreException('PostgreSQL outcome clear tenant metadata is inconsistent.');
        }
        return new TenantRef($type, $id);
    }

    private function context(
        OperationId $operationId,
        string $operationType,
        int $schemaVersion,
        ?TenantRef $tenant,
    ): StorageProtectionContext {
        return new StorageProtectionContext(
            StoragePurpose::OutcomePayload,
            $operationId->toString(),
            $operationId->toString(),
            $operationType,
            $schemaVersion,
            $tenant,
        );
    }
}
