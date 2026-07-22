<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\IdentityClock;
use DateTimeImmutable;

final readonly class SystemIdentityClock implements IdentityClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
