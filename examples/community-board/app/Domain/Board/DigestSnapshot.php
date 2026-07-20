<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class DigestSnapshot
{
    public function __construct(
        public int $postCount,
        public int $commentCount,
    ) {
        if ($postCount < 0 || $commentCount < 0) {
            throw new \InvalidArgumentException('Digest counts cannot be negative.');
        }
    }
}
