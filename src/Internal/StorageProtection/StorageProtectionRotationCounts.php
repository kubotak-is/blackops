<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

final readonly class StorageProtectionRotationCounts
{
    public function __construct(
        public int $selected,
        public int $rotated,
        public int $skipped,
        public int $failed,
    ) {}
}
