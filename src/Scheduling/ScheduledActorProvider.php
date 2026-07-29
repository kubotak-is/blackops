<?php

declare(strict_types=1);

namespace BlackOps\Scheduling;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ScheduleContext;

#[PublicApi]
interface ScheduledActorProvider
{
    public function actor(ScheduleContext $context): ?ActorRef;
}
