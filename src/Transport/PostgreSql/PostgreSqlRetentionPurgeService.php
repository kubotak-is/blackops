<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeResult;
use BlackOps\Core\Retention\RetentionPurgeService as RetentionPurgeServicePort;
use DateTimeImmutable;

final readonly class PostgreSqlRetentionPurgeService implements RetentionPurgeServicePort
{
    public function __construct(
        private RetentionPlanner $planner,
        private PostgreSqlTransportPayloadTombstoneService $transportPayloads,
        private PostgreSqlOutcomeRetentionDeleteService $outcomes,
        private PostgreSqlDeadLetterRetentionDeleteService $deadLetters,
    ) {}

    public function purge(
        RetentionPolicy $policy,
        RetentionPolicyRef $policyRef,
        RetentionActorRef $actor,
        DateTimeImmutable $now,
    ): RetentionPurgeResult {
        $plan = $this->planner->plan($policy, $now);

        return new RetentionPurgeResult(
            $plan,
            $this->transportPayloads->tombstone($plan, $policyRef, $actor),
            $this->deadLetters->delete($plan, $policyRef, $actor),
            $this->outcomes->delete($plan, $policyRef, $actor),
        );
    }
}
