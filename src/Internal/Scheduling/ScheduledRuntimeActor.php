<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\ActorRef;

final class ScheduledRuntimeActor
{
    public static function ref(): ActorRef
    {
        return new ActorRef('scheduled-runtime', 'system');
    }
}
