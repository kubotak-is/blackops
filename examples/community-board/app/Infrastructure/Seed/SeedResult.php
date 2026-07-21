<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

final readonly class SeedResult
{
    public function __construct(
        public int $users,
        public int $posts,
        public int $comments,
    ) {}
}
