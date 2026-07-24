<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class AddedComment
{
    public function __construct(
        public string $commentId,
        public string $postId,
        public string $authorId,
        public string $postOwnerId,
        public DateTimeImmutable $createdAt,
    ) {}
}
