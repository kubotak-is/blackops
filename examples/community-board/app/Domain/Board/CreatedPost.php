<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class CreatedPost
{
    public function __construct(
        public string $postId,
        public DateTimeImmutable $createdAt,
    ) {}
}
