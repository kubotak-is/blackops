<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use DateTimeImmutable;

final readonly class SeedComment
{
    public function __construct(
        public string $id,
        public string $postId,
        public string $authorId,
        public string $body,
        public DateTimeImmutable $createdAt,
    ) {}
}
