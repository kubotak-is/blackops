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

final readonly class PostgreSqlIdempotencyRetentionDeleteService
{
    private PostgreSqlIdempotencySchema $schema;
    private PostgreSqlDeferredOperationSchema $retentionSchema;

    public function __construct(
        private Connection $connection,
        private RetentionPurgeAuditPort $audit,
        string $schema = 'blackops',
        private ClockInterface $clock = new PostgreSqlSystemClock(),
        private PostgreSqlRetentionPurgeAuditIdGenerator $ids = new SymfonyRetentionPurgeAuditIdGenerator(),
    ) {
        $this->schema = new PostgreSqlIdempotencySchema($schema);
        $this->retentionSchema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function delete(RetentionPlan $plan, RetentionPolicyRef $policy, RetentionActorRef $actor): int
    {
        try {
            return $this->connection->transactional(function () use ($plan, $policy, $actor): int {
                $deleted = 0;
                foreach ($plan->forTarget(RetentionTarget::IdempotencyRecord) as $item) {
                    $count = (int) $this->connection->executeStatement(
                        "DELETE FROM {$this->schema->table()} r
                        WHERE r.operation_id = :operation_id
                            AND r.state = 'terminal'
                            AND r.expires_at <= :eligible_at
                            AND NOT EXISTS (
                                SELECT 1 FROM {$this->retentionSchema->retentionHoldsTable()} h
                                WHERE h.operation_id = r.operation_id AND h.released_at IS NULL
                            )",
                        [
                            'operation_id' => $item->operationId()->toString(),
                            'eligible_at' => $item->eligibleAt()->format('Y-m-d H:i:s.uP'),
                        ],
                    );
                    if ($count !== 1) {
                        continue;
                    }
                    $purgedAt = $this->clock->now();
                    $this->audit->record(
                        new RetentionPurgeAuditRecord(
                            $this->ids->generate($purgedAt),
                            $item->operationId(),
                            RetentionPurgeTarget::IdempotencyRecord,
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
                'Failed to delete PostgreSQL idempotency records for retention.',
                previous: $exception,
            );
        }
    }
}
