<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHold;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class PostgreSqlRetentionHoldStore implements RetentionHoldPort
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private ClockInterface $clock = new PostgreSqlSystemClock(),
        private PostgreSqlRetentionHoldIdGenerator $ids = new SymfonyRetentionHoldIdGenerator(),
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function place(
        OperationId $operationId,
        RetentionHoldCategory $category,
        string $reason,
        RetentionActorRef $placedBy,
        DateTimeImmutable $placedAt,
    ): RetentionHold {
        try {
            $id = $this->ids->generate($this->clock->now());
            $hold = new RetentionHold($id, $operationId, $category, $reason, $placedAt, $placedBy);
            $table = $this->schema->retentionHoldsTable();
            $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    hold_id,
                    operation_id,
                    category,
                    reason,
                    placed_at,
                    placed_by
                ) VALUES (
                    :hold_id,
                    :operation_id,
                    :category,
                    :reason,
                    :placed_at,
                    :placed_by
                )",
                [
                    'hold_id' => $hold->id()->toString(),
                    'operation_id' => $operationId->toString(),
                    'category' => $category->value,
                    'reason' => $hold->reason(),
                    'placed_at' => $this->formatTimestamp($hold->placedAt()),
                    'placed_by' => $placedBy->toString(),
                ],
            );

            return $hold;
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to place PostgreSQL retention hold.', previous: $exception);
        }
    }

    public function release(
        RetentionHoldId $holdId,
        RetentionActorRef $releasedBy,
        DateTimeImmutable $releasedAt,
    ): RetentionHold {
        try {
            $table = $this->schema->retentionHoldsTable();
            $row = $this->connection->fetchAssociative(
                "UPDATE {$table}
                    SET released_at = :released_at,
                        released_by = :released_by
                    WHERE hold_id = :hold_id
                        AND released_at IS NULL
                    RETURNING
                        hold_id::text AS hold_id,
                        operation_id::text AS operation_id,
                        category,
                        reason,
                        placed_at::text AS placed_at,
                        placed_by,
                        released_at::text AS released_at,
                        released_by",
                [
                    'hold_id' => $holdId->toString(),
                    'released_at' => $this->formatTimestamp($releasedAt),
                    'released_by' => $releasedBy->toString(),
                ],
            );

            if (!is_array($row)) {
                throw new DeferredTransportException('Retention hold is missing or already released.');
            }

            return $this->holdFromRow($row);
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to release PostgreSQL retention hold.', previous: $exception);
        }
    }

    public function activeFor(OperationId $operationId): array
    {
        $table = $this->schema->retentionHoldsTable();
        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                hold_id::text AS hold_id,
                operation_id::text AS operation_id,
                category,
                reason,
                placed_at::text AS placed_at,
                placed_by,
                released_at::text AS released_at,
                released_by
            FROM {$table}
            WHERE operation_id = :operation_id
                AND released_at IS NULL
            ORDER BY placed_at, hold_id",
            ['operation_id' => $operationId->toString()],
        );

        return array_map($this->holdFromRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function holdFromRow(array $row): RetentionHold
    {
        $releasedAt = $row['released_at'] === null ? null : $this->dateTime($row, 'released_at');
        $releasedBy = $row['released_by'] === null
            ? null
            : RetentionActorRef::fromString($this->string($row, 'released_by'));

        return new RetentionHold(
            RetentionHoldId::fromString($this->string($row, 'hold_id')),
            OperationId::fromString($this->string($row, 'operation_id')),
            RetentionHoldCategory::from($this->string($row, 'category')),
            $this->string($row, 'reason'),
            $this->dateTime($row, 'placed_at'),
            RetentionActorRef::fromString($this->string($row, 'placed_by')),
            $releasedAt,
            $releasedBy,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Retention hold row contains an invalid string field.');
        }

        return $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(array $row, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string($row, $key));
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
