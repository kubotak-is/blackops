<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use InvalidArgumentException;

final readonly class NativeWorkerSleeper implements WorkerSleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException('Worker sleep duration cannot be negative.');
        }

        usleep($milliseconds * 1_000);
    }
}
