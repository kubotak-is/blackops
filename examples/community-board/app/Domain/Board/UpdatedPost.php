<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

final readonly class UpdatedPost
{
    public function __construct(
        public string $postId,
        public DateTimeImmutable $updatedAt,
    ) {}
}
