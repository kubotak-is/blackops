<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\IdentityClock;
use DateTimeImmutable;

final class FrozenIdentityClock implements IdentityClock
{
    public function __construct(
        public DateTimeImmutable $current,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}
