<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use SensitiveParameter;

final readonly class PostgreSqlLeaseExpiredRecoveryStore
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSchema $schema,
        private PostgreSqlDeferredOperationLifecycleSql $sql,
        private PostgreSqlDeferredOperationMessageCodec $messages = new PostgreSqlDeferredOperationMessageCodec(),
    ) {}

    public function reserve(DateTimeImmutable $expiredAt): ?PostgreSqlLeaseExpiredReservation
    {
        $row = $this->lockedExpiredRunningRow($expiredAt);

        if ($row === null) {
            return null;
        }

        $operationId = OperationId::fromString($this->string($row, 'operation_id'));
        $fencingToken = $this->integer($row, 'fencing_token');
        $sequence = $this->integer($row, 'next_sequence');
        $message = $this->messages->fromRow($row);
        $attempt = new AttemptContext(
            AttemptId::fromString($this->string($row, 'current_attempt_id')),
            $this->integer($row, 'attempt_number'),
            new DateTimeImmutable($this->string($row, 'current_attempt_started_at')),
        );

        $this->markSupervising($operationId, $fencingToken, $sequence + 1, $expiredAt);

        return new PostgreSqlLeaseExpiredReservation(
            new OperationClaim($message, $this->claimToken($operationId, $fencingToken)),
            $sequence,
            $attempt,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockedExpiredRunningRow(DateTimeImmutable $expiredAt): ?array
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
                fencing_token,
                next_sequence,
                attempt_number,
                current_attempt_id::text AS current_attempt_id,
                current_attempt_started_at::text AS current_attempt_started_at
            FROM {$table}
            WHERE state = 'running'
                AND lease_expires_at <= :expired_at
                AND current_attempt_id IS NOT NULL
                AND current_attempt_started_at IS NOT NULL
            ORDER BY lease_expires_at, operation_id
            FOR UPDATE SKIP LOCKED
            LIMIT 1",
            ['expired_at' => $this->sql->formatTimestamp($expiredAt)],
        );

        return is_array($row) ? $row : null;
    }

    private function markSupervising(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        int $nextSequence,
        DateTimeImmutable $expiredAt,
    ): void {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = 'supervising',
                    next_sequence = :next_sequence,
                    state_version = state_version + 1,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    current_attempt_id = NULL,
                    current_attempt_started_at = NULL,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'
                    AND lease_expires_at <= :expired_at
                    AND current_attempt_id IS NOT NULL
                    AND current_attempt_started_at IS NOT NULL",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
                'next_sequence' => $nextSequence,
                'updated_at' => $this->sql->formatTimestamp($expiredAt),
                'expired_at' => $this->sql->formatTimestamp($expiredAt),
            ],
        );

        $this->sql->assertUpdated($updated);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Deferred operation row contains an invalid string field.');
        }

        return $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new DeferredTransportException('Deferred operation row contains an invalid integer field.');
        }

        return $row[$key];
    }

    private function claimToken(OperationId $operationId, #[SensitiveParameter] int $fencingToken): string
    {
        return $operationId->toString() . ':' . $fencingToken;
    }
}
