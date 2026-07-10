<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use SensitiveParameter;

final readonly class PostgreSqlDeferredOperationLeaseStore
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSchema $schema,
        private string $leaseOwner,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function selectEligible(DateTimeImmutable $claimedAt): ?array
    {
        $table = $this->schema->operationsTable();
        $row = $this->connection->fetchAssociative(
            "SELECT
                operation_id::text AS operation_id,
                operation_type,
                schema_version,
                convert_from(encoded_payload, 'UTF8') AS encoded_payload,
                convert_from(encoded_context, 'UTF8') AS encoded_context,
                available_at,
                fencing_token
            FROM {$table}
            WHERE state IN ('accepted', 'retry_scheduled')
                AND available_at <= :claimed_at
            ORDER BY available_at, operation_id
            FOR UPDATE SKIP LOCKED
            LIMIT 1",
            ['claimed_at' => $this->formatTimestamp($claimedAt)],
        );

        return is_array($row) ? $row : null;
    }

    public function markRunning(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        DateTimeImmutable $leaseExpiresAt,
    ): void {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = 'running',
                    state_version = state_version + 1,
                    lease_owner = :lease_owner,
                    lease_expires_at = :lease_expires_at,
                    fencing_token = :fencing_token,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id",
            [
                'operation_id' => $operationId->toString(),
                'lease_owner' => $this->leaseOwner,
                'lease_expires_at' => $this->formatTimestamp($leaseExpiresAt),
                'fencing_token' => $fencingToken,
                'updated_at' => $this->formatTimestamp($leaseExpiresAt),
            ],
        );

        $this->assertUpdated($updated, 'Deferred operation claim did not update exactly one row.');
    }

    public function heartbeat(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $updatedAt,
    ): void {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET lease_owner = :lease_owner,
                    lease_expires_at = :lease_expires_at,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
                'lease_owner' => $this->leaseOwner,
                'lease_expires_at' => $this->formatTimestamp($leaseExpiresAt),
                'updated_at' => $this->formatTimestamp($updatedAt),
            ],
        );

        $this->assertUpdated($updated, 'Deferred operation heartbeat claim is stale or not running.');
    }

    public function acknowledgeTerminal(OperationId $operationId, #[SensitiveParameter] int $fencingToken): void
    {
        $table = $this->schema->operationsTable();
        $acknowledged = $this->connection->fetchOne(
            "SELECT 1
                FROM {$table}
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state IN ('completed', 'rejected', 'failed', 'dead_lettered')
                    AND lease_owner IS NULL
                    AND lease_expires_at IS NULL
                    AND current_attempt_id IS NULL
                    AND current_attempt_started_at IS NULL
                FOR UPDATE",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
            ],
        );

        if ($acknowledged === false) {
            throw new DeferredTransportException('Deferred operation acknowledge claim is stale or not terminal.');
        }
    }

    public function releaseBeforeAttempt(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $updatedAt,
    ): void {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = 'accepted',
                    state_version = state_version + 1,
                    available_at = :available_at,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'
                    AND current_attempt_id IS NULL
                    AND current_attempt_started_at IS NULL",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
                'available_at' => $this->formatTimestamp($availableAt),
                'updated_at' => $this->formatTimestamp($updatedAt),
            ],
        );

        $this->assertUpdated($updated, 'Deferred operation release claim is stale or already started.');
    }

    private function assertUpdated(int|string $updated, string $message): void
    {
        if ((int) $updated !== 1) {
            throw new DeferredTransportException($message);
        }
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
