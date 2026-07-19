<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemIdentityClock implements IdentityClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
