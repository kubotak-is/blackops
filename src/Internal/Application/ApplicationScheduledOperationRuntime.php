<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Scheduling\ScheduledOperationRunner;

final readonly class ApplicationScheduledOperationRuntime
{
    public function __construct(
        public ScheduledOperationRunner $runner,
    ) {}
}
