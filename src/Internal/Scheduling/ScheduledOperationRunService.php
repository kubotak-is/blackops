<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

interface ScheduledOperationRunService
{
    public function run(): ScheduledOperationRunResult;
}
