<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlDeferredOperationLifecycleStore
{
    private PostgreSqlDeferredOperationSchema $schema;
    private PostgreSqlDeferredOperationLifecycleSql $sql;
    private PostgreSqlDeadLetterStore $deadLetters;
    private PostgreSqlLeaseExpiredRecoveryStore $leaseExpired;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
        $this->sql = new PostgreSqlDeferredOperationLifecycleSql($connection, $this->schema);
        $this->deadLetters = new PostgreSqlDeadLetterStore($connection, $this->schema, $this->sql);
        $this->leaseExpired = new PostgreSqlLeaseExpiredRecoveryStore($connection, $this->schema, $this->sql);
    }

    public function reserveAttemptStarted(
        OperationClaim $claim,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlAttemptStartedReservation {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'running');
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
                'updated_at' => $this->sql->formatTimestamp($updatedAt),
            ],
        );

        $this->sql->assertUpdated($updated);

        return new PostgreSqlAttemptStartedReservation($sequence, $attemptNumber);
    }

    public function recordCurrentAttempt(
        OperationClaim $claim,
        AttemptContext $attempt,
        DateTimeImmutable $updatedAt,
    ): void {
        $token = $this->sql->parseToken($claim);
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET current_attempt_id = :current_attempt_id,
                    current_attempt_started_at = :current_attempt_started_at,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND attempt_number = :attempt_number
                    AND state = 'running'",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'fencing_token' => $token,
                'attempt_number' => $attempt->number(),
                'current_attempt_id' => $attempt->id()->toString(),
                'current_attempt_started_at' => $this->sql->formatTimestamp($attempt->startedAt()),
                'updated_at' => $this->sql->formatTimestamp($updatedAt),
            ],
        );

        $this->sql->assertUpdated($updated);
    }

    public function reserveCompleted(
        OperationClaim $claim,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlCompletionReservation {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'running');
        $sequence = (int) $row['next_sequence'];

        $this->sql->markTerminal(
            new PostgreSqlTerminalTransition(
                $claim->message()->operationId(),
                $token,
                'running',
                'completed',
                $sequence + 2,
                $updatedAt,
            ),
        );

        return new PostgreSqlCompletionReservation($sequence, $sequence + 1);
    }

    public function reserveRejected(OperationClaim $claim, DateTimeImmutable $updatedAt): PostgreSqlRejectionReservation
    {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'running');
        $sequence = (int) $row['next_sequence'];

        $this->sql->markTerminal(
            new PostgreSqlTerminalTransition(
                $claim->message()->operationId(),
                $token,
                'running',
                'rejected',
                $sequence + 1,
                $updatedAt,
            ),
        );

        return new PostgreSqlRejectionReservation($sequence);
    }

    public function reserveFailed(OperationClaim $claim, DateTimeImmutable $updatedAt): PostgreSqlFailureReservation
    {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'running');
        $sequence = (int) $row['next_sequence'];
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
                    AND state = 'running'",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'fencing_token' => $token,
                'next_sequence' => $sequence + 1,
                'updated_at' => $this->sql->formatTimestamp($updatedAt),
            ],
        );

        $this->sql->assertUpdated($updated);

        return new PostgreSqlFailureReservation($sequence);
    }

    public function reserveRetryScheduled(
        OperationClaim $claim,
        DateTimeImmutable $scheduledAt,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlRetryScheduledReservation {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'supervising');
        $sequence = (int) $row['next_sequence'];
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = 'retry_scheduled',
                    next_sequence = :next_sequence,
                    state_version = state_version + 1,
                    available_at = :available_at,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    current_attempt_id = NULL,
                    current_attempt_started_at = NULL,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = 'supervising'",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'fencing_token' => $token,
                'next_sequence' => $sequence + 1,
                'available_at' => $this->sql->formatTimestamp($scheduledAt),
                'updated_at' => $this->sql->formatTimestamp($updatedAt),
            ],
        );

        $this->sql->assertUpdated($updated);

        return new PostgreSqlRetryScheduledReservation($sequence);
    }

    public function reserveOperationFailed(
        OperationClaim $claim,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlOperationFailedReservation {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'supervising');
        $sequence = (int) $row['next_sequence'];

        $this->sql->markTerminal(
            new PostgreSqlTerminalTransition(
                $claim->message()->operationId(),
                $token,
                'supervising',
                'failed',
                $sequence + 1,
                $updatedAt,
            ),
        );

        return new PostgreSqlOperationFailedReservation($sequence);
    }

    public function reserveDeadLettered(
        OperationClaim $claim,
        OperationDeadLetteredData $data,
        DateTimeImmutable $updatedAt,
    ): PostgreSqlDeadLetteredReservation {
        $token = $this->sql->parseToken($claim);
        $row = $this->sql->lockedRow($claim->message()->operationId(), $token, 'supervising');
        $sequence = (int) $row['next_sequence'];

        $this->sql->markTerminal(
            new PostgreSqlTerminalTransition(
                $claim->message()->operationId(),
                $token,
                'supervising',
                'dead_lettered',
                $sequence + 1,
                $updatedAt,
            ),
        );

        $this->deadLetters->insert($claim, $data);

        return new PostgreSqlDeadLetteredReservation($sequence);
    }

    public function reserveLeaseExpired(DateTimeImmutable $expiredAt): ?PostgreSqlLeaseExpiredReservation
    {
        return $this->leaseExpired->reserve($expiredAt);
    }
}
