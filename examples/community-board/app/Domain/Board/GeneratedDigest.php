<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class GeneratedDigest
{
    public function __construct(
        public string $id,
        public string $requestedUserId,
        public string $week,
        public string $content,
        public int $postCount,
        public int $commentCount,
        public DateTimeImmutable $createdAt,
    ) {}
}
