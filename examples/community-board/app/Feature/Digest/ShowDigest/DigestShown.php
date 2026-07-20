<?php

declare(strict_types=1);

namespace App\Feature\Digest\ShowDigest;

use BlackOps\Core\Outcome;

final readonly class DigestShown implements Outcome
{
    public function __construct(
        public string $digestId,
        public string $week,
        public string $content,
        public int $postCount,
        public int $commentCount,
        public string $createdAt,
    ) {}
}
