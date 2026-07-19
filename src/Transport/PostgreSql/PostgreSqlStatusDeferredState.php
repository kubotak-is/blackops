<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;

final readonly class PostgreSqlStatusDeferredState
{
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public string $operationId,
        public string $operationType,
        public int $schemaVersion,
        public LifecycleState $state,
        public int $nextSequence,
        public bool $payloadPurged,
        public int $attemptNumber,
        public ?string $currentAttemptId,
        public ?DateTimeImmutable $currentAttemptStartedAt,
        public DateTimeImmutable $availableAt,
    ) {}
}
