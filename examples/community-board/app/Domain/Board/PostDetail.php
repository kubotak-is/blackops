<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class PostDetail
{
    public function __construct(
        public string $id,
        public string $authorId,
        public string $authorDisplayName,
        public string $title,
        public string $body,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
