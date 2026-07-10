<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\OperationId;
use Doctrine\DBAL\Connection;
use SensitiveParameter;

final readonly class PostgreSqlDeferredOperationLifecycleSql
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSchema $schema,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lockedRow(OperationId $operationId, #[SensitiveParameter] int $fencingToken, string $state): array
    {
        $table = $this->schema->operationsTable();
        $row = $this->connection->fetchAssociative(
            "SELECT next_sequence, attempt_number
                FROM {$table}
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = :state
                FOR UPDATE",
            [
                'operation_id' => $operationId->toString(),
                'fencing_token' => $fencingToken,
                'state' => $state,
            ],
        );

        if (!is_array($row)) {
            throw new DeferredTransportException('Deferred operation claim is stale or in an unexpected state.');
        }

        return $row;
    }

    public function markTerminal(PostgreSqlTerminalTransition $transition): void
    {
        $table = $this->schema->operationsTable();
        $updated = $this->connection->executeStatement(
            "UPDATE {$table}
                SET state = :to_state,
                    next_sequence = :next_sequence,
                    state_version = state_version + 1,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    updated_at = :updated_at
                WHERE operation_id = :operation_id
                    AND fencing_token = :fencing_token
                    AND state = :from_state",
            [
                'operation_id' => $transition->operationId->toString(),
                'fencing_token' => $transition->fencingToken,
                'from_state' => $transition->fromState,
                'to_state' => $transition->toState,
                'next_sequence' => $transition->nextSequence,
                'updated_at' => $this->formatTimestamp($transition->updatedAt),
            ],
        );

        $this->assertUpdated($updated);
    }

    public function parseToken(OperationClaim $claim): int
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

    public function assertUpdated(int|string $updated): void
    {
        if ((int) $updated !== 1) {
            throw new DeferredTransportException('Deferred operation state update did not update exactly one row.');
        }
    }

    public function formatTimestamp(\DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
