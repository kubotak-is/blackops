<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\Retention\RetentionTarget;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class PostgreSqlTransportPayloadTombstoneService
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        private RetentionPurgeAuditPort $audit,
        string $schema = 'blackops',
        private ClockInterface $clock = new PostgreSqlSystemClock(),
        private PostgreSqlRetentionPurgeAuditIdGenerator $ids = new SymfonyRetentionPurgeAuditIdGenerator(),
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function tombstone(RetentionPlan $plan, RetentionPolicyRef $policy, RetentionActorRef $actor): int
    {
        try {
            $purged = 0;

            $this->connection->beginTransaction();

            try {
                foreach ($plan->forTarget(RetentionTarget::TransportPayload) as $item) {
                    $purgedAt = $this->clock->now();

                    if ($this->tombstoneOperation($item->operationId()->toString(), $purgedAt) !== 1) {
                        continue;
                    }

                    $this->audit->record(
                        new RetentionPurgeAuditRecord(
                            $this->ids->generate($purgedAt),
                            $item->operationId(),
                            RetentionPurgeTarget::TransportPayload,
                            1,
                            $policy,
                            $purgedAt,
                            $actor,
                        ),
                    );
                    ++$purged;
                }

                $this->connection->commit();
            } catch (Throwable $exception) {
                $this->connection->rollBack();

                throw $exception;
            }

            return $purged;
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to tombstone PostgreSQL transport payload.',
                previous: $exception,
            );
        }
    }

    private function tombstoneOperation(string $operationId, DateTimeImmutable $purgedAt): int
    {
        $operations = $this->schema->operationsTable();
        $holds = $this->schema->retentionHoldsTable();

        return (int) $this->connection->executeStatement(
            "UPDATE {$operations} o
            SET encoded_payload = NULL,
                encoded_context = NULL,
                payload_purged_at = :payload_purged_at,
                updated_at = :updated_at
            WHERE o.operation_id = :operation_id
                AND o.state IN ('completed', 'rejected', 'failed', 'dead_lettered')
                AND o.encoded_payload IS NOT NULL
                AND o.encoded_context IS NOT NULL
                AND o.payload_purged_at IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$holds} h
                    WHERE h.operation_id = o.operation_id
                        AND h.released_at IS NULL
                )",
            [
                'operation_id' => $operationId,
                'payload_purged_at' => $this->formatTimestamp($purgedAt),
                'updated_at' => $this->formatTimestamp($purgedAt),
            ],
        );
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
