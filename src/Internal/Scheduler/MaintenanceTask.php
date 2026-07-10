<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use DateTimeImmutable;

interface MaintenanceTask
{
    public function name(): string;

    public function run(DateTimeImmutable $now): MaintenanceTaskResult;
}
