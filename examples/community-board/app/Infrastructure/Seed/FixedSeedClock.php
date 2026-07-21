<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use App\Domain\Board\BoardClock;
use App\Identity\IdentityClock;
use DateTimeImmutable;

final readonly class FixedSeedClock implements BoardClock, IdentityClock
{
    public function __construct(
        private DateTimeImmutable $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
