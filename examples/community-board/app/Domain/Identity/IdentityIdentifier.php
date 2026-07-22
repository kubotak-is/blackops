<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface IdentityIdentifier
{
    public function generate(): string;
}
