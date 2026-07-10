<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;

#[PublicApi]
interface RetentionPurgeService
{
    public function purge(
        RetentionPolicy $policy,
        RetentionPolicyRef $policyRef,
        RetentionActorRef $actor,
        DateTimeImmutable $now,
    ): RetentionPurgeResult;
}
