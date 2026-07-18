<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsDeadLetter
{
    public function __construct(
        public string $operationId,
        public ?string $finalAttemptId,
        public ?int $finalAttemptNumber,
        public string $reasonType,
        public string $movedAt,
    ) {}
}
