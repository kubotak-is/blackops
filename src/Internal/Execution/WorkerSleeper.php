<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

interface WorkerSleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
