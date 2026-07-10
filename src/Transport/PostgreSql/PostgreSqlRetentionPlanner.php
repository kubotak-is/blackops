<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionTarget;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlRetentionPlanner implements RetentionPlanner
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private int $limitPerTarget = 1_000,
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function plan(RetentionPolicy $policy, DateTimeImmutable $now): RetentionPlan
    {
        try {
            return new RetentionPlan([
                ...$this->transportPayloadItems($policy, $now),
                ...$this->deadLetterItems($policy, $now),
            ]);
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to plan PostgreSQL retention purge.', previous: $exception);
        }
    }

    /**
     * @return list<RetentionPlanItem>
     */
    private function transportPayloadItems(RetentionPolicy $policy, DateTimeImmutable $now): array
    {
        $operations = $this->schema->operationsTable();
        $holds = $this->schema->retentionHoldsTable();
        $period = $policy->transportPayloadRetention();
        $cutoff = $now->modify('-' . $period->secondsValue() . ' seconds');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                operation_id::text AS operation_id,
                updated_at::text AS basis_at
            FROM {$operations} o
            WHERE o.state IN ('completed', 'rejected', 'failed', 'dead_lettered')
                AND o.encoded_payload IS NOT NULL
                AND o.encoded_context IS NOT NULL
                AND o.payload_purged_at IS NULL
                AND o.updated_at <= :cutoff
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$holds} h
                    WHERE h.operation_id = o.operation_id
                        AND h.released_at IS NULL
                )
            ORDER BY o.updated_at, o.operation_id
            LIMIT {$this->limitPerTarget}",
            ['cutoff' => $this->formatTimestamp($cutoff)],
        );

        return array_map(fn(array $row): RetentionPlanItem => $this->item(
            $row,
            RetentionTarget::TransportPayload,
            $period->secondsValue(),
        ), $rows);
    }

    /**
     * @return list<RetentionPlanItem>
     */
    private function deadLetterItems(RetentionPolicy $policy, DateTimeImmutable $now): array
    {
        $deadLetters = $this->schema->deadLettersTable();
        $holds = $this->schema->retentionHoldsTable();
        $period = $policy->deadLetterRetention();
        $cutoff = $now->modify('-' . $period->secondsValue() . ' seconds');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                operation_id::text AS operation_id,
                moved_at::text AS basis_at
            FROM {$deadLetters} d
            WHERE d.moved_at <= :cutoff
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$holds} h
                    WHERE h.operation_id = d.operation_id
                        AND h.released_at IS NULL
                )
            ORDER BY d.moved_at, d.operation_id
            LIMIT {$this->limitPerTarget}",
            ['cutoff' => $this->formatTimestamp($cutoff)],
        );

        return array_map(fn(array $row): RetentionPlanItem => $this->item(
            $row,
            RetentionTarget::DeadLetter,
            $period->secondsValue(),
        ), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function item(array $row, RetentionTarget $target, int $seconds): RetentionPlanItem
    {
        $basisAt = new DateTimeImmutable($this->string($row, 'basis_at'));

        return new RetentionPlanItem(
            OperationId::fromString($this->string($row, 'operation_id')),
            $target,
            $basisAt,
            $basisAt->modify('+' . $seconds . ' seconds'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Retention planner row contains an invalid string field.');
        }

        return $row[$key];
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
