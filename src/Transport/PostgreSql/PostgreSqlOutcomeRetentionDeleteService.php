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
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class PostgreSqlOutcomeRetentionDeleteService
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

    public function delete(RetentionPlan $plan, RetentionPolicyRef $policy, RetentionActorRef $actor): int
    {
        try {
            return $this->connection->transactional(function () use ($plan, $policy, $actor): int {
                $deleted = 0;

                foreach ($plan->forTarget(RetentionTarget::Outcome) as $item) {
                    $purgedAt = $this->clock->now();

                    if ($this->deleteOutcome($item->operationId()->toString()) !== 1) {
                        continue;
                    }

                    $this->audit->record(
                        new RetentionPurgeAuditRecord(
                            $this->ids->generate($purgedAt),
                            $item->operationId(),
                            RetentionPurgeTarget::Outcome,
                            1,
                            $policy,
                            $purgedAt,
                            $actor,
                        ),
                    );
                    ++$deleted;
                }

                return $deleted;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to delete PostgreSQL outcome for retention.',
                previous: $exception,
            );
        }
    }

    private function deleteOutcome(string $operationId): int
    {
        $outcomes = $this->schema->outcomesTable();
        $holds = $this->schema->retentionHoldsTable();

        return (int) $this->connection->executeStatement(
            "DELETE FROM {$outcomes} o
            WHERE o.operation_id = :operation_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$holds} h
                    WHERE h.operation_id = o.operation_id
                        AND h.released_at IS NULL
                )",
            ['operation_id' => $operationId],
        );
    }
}
