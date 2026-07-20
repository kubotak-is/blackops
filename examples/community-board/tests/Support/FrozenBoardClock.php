<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Board\BoardClock;
use DateTimeImmutable;

final class FrozenBoardClock implements BoardClock
{
    public function __construct(
        public DateTimeImmutable $current,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}
