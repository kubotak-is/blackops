<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use SensitiveParameter;

final readonly class PostgreSqlDeferredOperationLifecycleStore
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function reserveAttemptStarted(
        OperationClaim $claim,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlAttemptStartedReservation {
        $token = $this->parseToken($claim);
        $row = $this->lockedRunningRow($claim->message()->operationId(), $token);
        $sequence = (int) $row['next_sequence'];
        $attemptNumber = (int) $row['attempt_number'] + 1;
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET attempt_number = :attempt_number,
                    next_sequence = :next_sequence,
                    state_version = state_version + 1,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'fencing_token' => $token,
                'attempt_number' => $attemptNumber,
                'next_sequence' => $sequence + 1,
                'updated_at' => $this->formatTimestamp($updatedAt),
            ],
        );

        $this->assertUpdated($updated);

        return new PostgreSqlAttemptStartedReservation($sequence, $attemptNumber);
    }

    public function reserveCompleted(
        OperationClaim $claim,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlCompletionReservation {
        $token = $this->parseToken($claim);
        $row = $this->lockedRunningRow($claim->message()->operationId(), $token);
        $sequence = (int) $row['next_sequence'];

        $this->markTerminal($claim->message()->operationId(), $token, 'completed', $sequence + 2, $updatedAt);

        return new PostgreSqlCompletionReservation($sequence, $sequence + 1);
    }

    public function reserveRejected(OperationClaim $claim, DateTimeImmutable $updatedAt): PostgreSqlRejectionReservation
    {
        $token = $this->parseToken($claim);
        $row = $this->lockedRunningRow($claim->message()->operationId(), $token);
        $sequence = (int) $row['next_sequence'];

        $this->markTerminal($claim->message()->operationId(), $token, 'rejected', $sequence + 1, $updatedAt);

        return new PostgreSqlRejectionReservation($sequence);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedRunningRow(OperationId $operationId, #[SensitiveParameter] int $fencingToken): array
    {
        $table = $this->schema->operationsTable();
        $row = $this->connection->fetchAssociative(
            "SELECT next_sequence, attempt_number
                FROM {$table}
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'
                FOR UPDATE",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
            ],
        );

        if (!is_array($row)) {
            throw new DeferredTransportException('Deferred operation claim is stale or not running.');
        }

        return $row;
    }

    private function markTerminal(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        string $state,
        int $nextSequence,
        DateTimeImmutable $updatedAt,
    ): void {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = :state,
                    next_sequence = :next_sequence,
                    state_version = state_version + 1,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'running'",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
                'state' => $state,
                'next_sequence' => $nextSequence,
                'updated_at' => $this->formatTimestamp($updatedAt),
            ],
        );

        $this->assertUpdated($updated);
    }

    private function parseToken(OperationClaim $claim): int
    {
        $parts = explode(':', $claim->claimToken(), limit: 2);

        if (count($parts) !== 2 || $parts[0] !== $claim->message()->operationId()->toString()) {
            throw new DeferredTransportException('Deferred operation claim token does not match the operation.');
        }

        if (!ctype_digit($parts[1]) || $parts[1] === '0') {
            throw new DeferredTransportException('Deferred operation claim token has an invalid fencing token.');
        }

        return (int) $parts[1];
    }

    private function assertUpdated(int|string $updated): void
    {
        if ((int) $updated !== 1) {
            throw new DeferredTransportException('Deferred operation state update did not update exactly one row.');
        }
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
