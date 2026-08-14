<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeSchema;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlTenantScopedOutcomeReader implements TenantScopedOutcomeReader
{
    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
        string $schema = 'blackops',
        ?PostgreSqlOutcomeCodec $codec = null,
    ) {
        $this->schema = new PostgreSqlOutcomeSchema($schema);
        $this->codec = $codec ?? new PostgreSqlOutcomeCodec();
    }

    private PostgreSqlOutcomeSchema $schema;
    private PostgreSqlOutcomeCodec $codec;

    public function findForTenant(OperationId $operationId, ?TenantRef $tenant): ?OutcomeRecord
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT outcome.outcome_type, outcome.schema_version,
                        outcome.tenant_type AS outcome_tenant_type,
                        outcome.tenant_id AS outcome_tenant_id,
                        o.operation_type, o.schema_version AS operation_schema_version,
                        o.tenant_type AS operation_tenant_type,
                        o.tenant_id AS operation_tenant_id,
                        outcome.encoded_payload,
                        outcome.completed_at::text AS completed_at
                 FROM {$this->schema->table()} outcome
                 JOIN {$this->schema->operationsTable()} o USING (operation_id)
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
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        if ($row === false) {
            return null;
        }
        try {
            $type = $this->string($row, 'outcome_type');
            $schemaVersion = $this->integer($row, 'schema_version');
            $operationType = $this->string($row, 'operation_type');
            $operationSchemaVersion = $this->integer($row, 'operation_schema_version');
            $clearTenant = $this->tenant($row);
        } catch (Throwable) {
            throw OperationOutcomeQueryException::integrityFailed();
        }
        try {
            $payload = $this->protection->decrypt(
                PostgreSqlBytea::string($row['encoded_payload'] ?? null),
                new StorageProtectionContext(
                    StoragePurpose::OutcomePayload,
                    $operationId->toString(),
                    $operationId->toString(),
                    $operationType,
                    $operationSchemaVersion,
                    $clearTenant,
                ),
            );
        } catch (Throwable) {
            throw OperationOutcomeQueryException::decodeFailed();
        }
        try {
            $outcome = $this->codec->decode($type, $schemaVersion, $payload);
        } catch (OperationOutcomeQueryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationOutcomeQueryException::decodeFailed();
        }
        try {
            $completedAt = new DateTimeImmutable($this->string($row, 'completed_at'));
        } catch (Throwable) {
            throw OperationOutcomeQueryException::integrityFailed();
        }

        return new OutcomeRecord($operationId, $outcome, $completedAt);
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new \InvalidArgumentException('Invalid PostgreSQL outcome field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new \InvalidArgumentException('Invalid PostgreSQL outcome integer field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function tenant(array $row): ?TenantRef
    {
        $type = $row['outcome_tenant_type'] ?? null;
        $id = $row['outcome_tenant_id'] ?? null;
        $operationType = $row['operation_tenant_type'] ?? null;
        $operationId = $row['operation_tenant_id'] ?? null;
        if ($type === null && $id === null) {
            if ($operationType !== null || $operationId !== null) {
                throw OperationOutcomeQueryException::integrityFailed();
            }
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw OperationOutcomeQueryException::integrityFailed();
        }
        if ($operationType !== $type || $operationId !== $id) {
            throw OperationOutcomeQueryException::integrityFailed();
        }
        return new TenantRef($type, $id);
    }
}
