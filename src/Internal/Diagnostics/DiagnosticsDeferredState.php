<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Journal\LifecycleState;

final readonly class DiagnosticsDeferredState
{
    public function __construct(
        public string $operationId,
        public string $type,
        public int $schemaVersion,
        public LifecycleState $state,
        public int $nextSequence,
        public bool $payloadPurged,
        public int $attemptNumber,
        public ?string $currentAttemptId,
        public ?string $currentAttemptStartedAt,
    ) {}
}
