<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class CommentDetail
{
    public function __construct(
        public string $id,
        public string $postId,
        public string $authorId,
        public string $authorDisplayName,
        public string $body,
        public DateTimeImmutable $createdAt,
    ) {}
}
