<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;

#[PublicApi]
interface RetentionPlanner
{
    public function plan(RetentionPolicy $policy, DateTimeImmutable $now): RetentionPlan;
}
