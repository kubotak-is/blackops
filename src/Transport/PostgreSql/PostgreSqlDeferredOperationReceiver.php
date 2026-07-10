<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Execution\OperationReceiver;
use BlackOps\Core\Identifier\OperationId;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use SensitiveParameter;
use Throwable;

final readonly class PostgreSqlDeferredOperationReceiver implements OperationReceiver
{
    private PostgreSqlDeferredOperationSchema $schema;
    private DateInterval $leaseDuration;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private string $leaseOwner = 'blackops-worker',
        int $leaseSeconds = 60,
    ) {
        if ($leaseOwner === '') {
            throw new InvalidArgumentException('Lease owner must not be empty.');
        }

        if ($leaseSeconds < 1) {
            throw new InvalidArgumentException('Lease duration must be positive.');
        }

        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
        $this->leaseDuration = new DateInterval('PT' . $leaseSeconds . 'S');
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to migrate PostgreSQL deferred operation receiver schema.',
                previous: $exception,
            );
        }
    }

    public function claim(ClaimRequest $request): ?OperationClaim
    {
        try {
            return $this->connection->transactional(function () use ($request): ?OperationClaim {
                $row = $this->selectEligible($request->claimedAt());

                if ($row === null) {
                    return null;
                }

                $fencingToken = (int) $row['fencing_token'] + 1;
                $leaseExpiresAt = $request->claimedAt()->add($this->leaseDuration);
                $message = $this->messageFromRow($row);

                $this->markRunning($message->operationId(), $fencingToken, $leaseExpiresAt);

                return new OperationClaim($message, $this->claimToken($message->operationId(), $fencingToken));
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to claim PostgreSQL deferred operation.',
                previous: $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectEligible(DateTimeImmutable $claimedAt): ?array
    {
        $table = $this->schema->operationsTable();
        $sql = "SELECT
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
            LIMIT 1";

        $row = $this->connection->fetchAssociative($sql, [
            'claimed_at' => $this->formatTimestamp($claimedAt),
        ]);

        return is_array($row) ? $row : null;
    }

    private function markRunning(
        OperationId $operationId,
        #[SensitiveParameter]
        int $fencingToken,
        DateTimeImmutable $leaseExpiresAt,
    ): void {
        $table = $this->schema->operationsTable();
        $sql = "UPDATE {$table}
            SET state = 'running',
                state_version = state_version + 1,
                lease_owner = :lease_owner,
                lease_expires_at = :lease_expires_at,
                fencing_token = :fencing_token,
                updated_at = :updated_at
            WHERE operation_id = :operation_id";

        $updated = $this->connection->executeStatement($sql, [
            'operation_id' => $operationId->toString(),
            'lease_owner' => $this->leaseOwner,
            'lease_expires_at' => $this->formatTimestamp($leaseExpiresAt),
            'fencing_token' => $fencingToken,
            'updated_at' => $this->formatTimestamp($leaseExpiresAt),
        ]);

        if ((int) $updated !== 1) {
            throw new DeferredTransportException('Deferred operation claim did not update exactly one row.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function messageFromRow(array $row): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($this->string($row, 'operation_id')),
            $this->string($row, 'operation_type'),
            $this->integer($row, 'schema_version'),
            $this->string($row, 'encoded_payload'),
            $this->string($row, 'encoded_context'),
            new DateTimeImmutable($this->string($row, 'available_at')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Claimed operation row contains an invalid string field.');
        }

        return $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new DeferredTransportException('Claimed operation row contains an invalid integer field.');
        }

        return $row[$key];
    }

    private function claimToken(OperationId $operationId, #[SensitiveParameter] int $fencingToken): string
    {
        return $operationId->toString() . ':' . $fencingToken;
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
