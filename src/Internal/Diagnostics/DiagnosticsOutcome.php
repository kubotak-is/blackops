<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsOutcome
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $type,
        public ?string $completedAt,
        public string $source,
        public array $data,
    ) {}
}
