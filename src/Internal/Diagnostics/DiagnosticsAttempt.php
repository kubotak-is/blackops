<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsAttempt
{
    /** @param list<int> $events */
    public function __construct(
        public string $attemptId,
        public int $number,
        public string $startedAt,
        public array $events,
    ) {}
}
