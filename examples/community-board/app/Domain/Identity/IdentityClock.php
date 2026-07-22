<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use DateTimeImmutable;

interface IdentityClock
{
    public function now(): DateTimeImmutable;
}
