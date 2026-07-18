<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Journal\LifecycleState;

final readonly class PostgreSqlDiagnosticsDeferredState
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
