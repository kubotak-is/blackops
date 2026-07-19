<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;

interface IdentityClock
{
    public function now(): DateTimeImmutable;
}
