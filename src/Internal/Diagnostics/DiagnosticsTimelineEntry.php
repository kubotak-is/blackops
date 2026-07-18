<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsTimelineEntry
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public int $sequence,
        public string $event,
        public string $occurredAt,
        public ?string $attemptId,
        public ?int $attemptNumber,
        public array $data,
    ) {}
}
