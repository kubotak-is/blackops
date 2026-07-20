<?php

declare(strict_types=1);

namespace App\Feature\Digest\GenerateWeeklyDigest;

use BlackOps\Core\Outcome;

final readonly class DigestGenerated implements Outcome
{
    public function __construct(
        public string $digestId,
        public string $week,
        public int $postCount,
        public int $commentCount,
        public string $createdAt,
    ) {}
}
