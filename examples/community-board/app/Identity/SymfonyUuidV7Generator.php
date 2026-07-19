<?php

declare(strict_types=1);

namespace App\Identity;

use Symfony\Component\Uid\UuidV7;

final readonly class SymfonyUuidV7Generator implements UuidGenerator
{
    public function generate(): string
    {
        return UuidV7::generate();
    }
}
