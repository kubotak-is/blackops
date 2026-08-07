<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeSchema;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlTenantScopedOutcomeReader implements TenantScopedOutcomeReader
{
    public function __construct(
        private Connection $connection,
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
                "SELECT outcome_type, schema_version, encoded_payload,
                        completed_at::text AS completed_at
                 FROM {$this->schema->table()}
                 WHERE operation_id = :operation_id
                   AND tenant_type IS NOT DISTINCT FROM :tenant_type
                   AND tenant_id IS NOT DISTINCT FROM :tenant_id",
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
            $payload = PostgreSqlBytea::string($row['encoded_payload'] ?? null);
        } catch (Throwable) {
            throw OperationOutcomeQueryException::integrityFailed();
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
}
