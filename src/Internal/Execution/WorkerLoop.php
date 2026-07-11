<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

interface WorkerLoop
{
    public function run(?int $maximumIterations = null, int $idleSleepMilliseconds = 1_000): int;
}
