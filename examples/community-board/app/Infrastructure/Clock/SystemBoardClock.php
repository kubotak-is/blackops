<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Domain\Board\BoardClock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemBoardClock implements BoardClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
