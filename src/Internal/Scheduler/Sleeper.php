<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

interface Sleeper
{
    public function sleep(int $seconds): void;
}
