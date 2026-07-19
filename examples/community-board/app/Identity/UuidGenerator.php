<?php

declare(strict_types=1);

namespace App\Identity;

interface UuidGenerator
{
    public function generate(): string;
}
