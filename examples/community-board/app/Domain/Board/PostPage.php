<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class PostPage
{
    /** @param list<PostSummary> $posts */
    public function __construct(
        public array $posts,
        public int $total,
    ) {}
}
