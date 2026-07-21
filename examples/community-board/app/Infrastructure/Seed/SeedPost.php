<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use DateTimeImmutable;

final readonly class SeedPost
{
    public function __construct(
        public string $id,
        public string $authorId,
        public string $title,
        public string $body,
        public DateTimeImmutable $createdAt,
    ) {}
}
