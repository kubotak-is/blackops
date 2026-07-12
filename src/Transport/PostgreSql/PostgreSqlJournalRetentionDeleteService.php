<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\Retention\RetentionTarget;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class PostgreSqlJournalRetentionDeleteService
{
    private PostgreSqlDeferredOperationSchema $schema;
    private PostgreSqlJournalSchema $journalSchema;

    public function __construct(
        private Connection $connection,
        private RetentionPurgeAuditPort $audit,
        string $schema = 'blackops',
        private ClockInterface $clock = new PostgreSqlSystemClock(),
        private PostgreSqlRetentionPurgeAuditIdGenerator $ids = new SymfonyRetentionPurgeAuditIdGenerator(),
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
        $this->journalSchema = new PostgreSqlJournalSchema($schema);
    }

    public function delete(RetentionPlan $plan, RetentionPolicyRef $policy, RetentionActorRef $actor): int
    {
        try {
            return $this->connection->transactional(function () use ($plan, $policy, $actor): int {
                $deleted = 0;

                foreach ($plan->forTarget(RetentionTarget::Journal) as $item) {
                    $affected = $this->deleteJournal($item);
                    if ($affected === 0) {
                        continue;
                    }

                    $purgedAt = $this->clock->now();
                    $this->audit->record(
                        new RetentionPurgeAuditRecord(
                            $this->ids->generate($purgedAt),
                            $item->operationId(),
                            RetentionPurgeTarget::Journal,
                            $affected,
                            $policy,
                            $purgedAt,
                            $actor,
                        ),
                    );
                    $deleted += $affected;
                }

                return $deleted;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to delete PostgreSQL journal for retention.',
                previous: $exception,
            );
        }
    }

    private function deleteJournal(RetentionPlanItem $item): int
    {
        $journal = $this->journalSchema->journalTable();
        $holds = $this->schema->retentionHoldsTable();
        $affected = $this->connection->fetchOne(
            "WITH eligible AS (
                SELECT j.operation_id
                FROM {$journal} j
                WHERE j.operation_id = :operation_id
                GROUP BY j.operation_id
                HAVING MAX(j.occurred_at) = :basis_at
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$holds} h
                        WHERE h.operation_id = j.operation_id
                            AND h.released_at IS NULL
                    )
            ), deleted AS (
                DELETE FROM {$journal} j
                USING eligible e
                WHERE j.operation_id = e.operation_id
                RETURNING 1
            )
            SELECT count(*) FROM deleted",
            [
                'operation_id' => $item->operationId()->toString(),
                'basis_at' => $item->basisAt()->format('Y-m-d H:i:s.uP'),
            ],
        );

        return (int) $affected;
    }
}
