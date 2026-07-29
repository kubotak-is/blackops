<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

final readonly class ScheduledOperationRunResult
{
    public function __construct(
        public int $evaluated,
        public int $accepted,
        public int $skippedMisfire,
        public int $skippedOverlap,
        public int $failed,
    ) {}

    public function status(): string
    {
        return $this->failed === 0 ? 'ok' : 'failed';
    }
}
