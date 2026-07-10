<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeService;
use DateTimeImmutable;

final readonly class RetentionMaintenanceTask implements MaintenanceTask
{
    public const NAME = 'retention';

    public function __construct(
        private RetentionPurgeService $purge,
        private RetentionPolicy $policy,
        private RetentionPolicyRef $policyRef,
        private RetentionActorRef $actor,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        $result = $this->purge->purge($this->policy, $this->policyRef, $this->actor, $now);

        return new MaintenanceTaskResult(self::NAME, $result->totalAffected(), 'Retention purge completed.');
    }
}
