<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

interface BoardClock
{
    public function now(): DateTimeImmutable;
}
