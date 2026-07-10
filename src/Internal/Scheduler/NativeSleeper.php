<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use InvalidArgumentException;

final readonly class NativeSleeper implements Sleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Sleep seconds must not be negative.');
        }

        sleep($seconds);
    }
}
