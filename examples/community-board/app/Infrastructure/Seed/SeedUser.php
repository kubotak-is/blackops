<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use DateTimeImmutable;

final readonly class SeedUser
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $password,
        public DateTimeImmutable $createdAt,
    ) {}
}
