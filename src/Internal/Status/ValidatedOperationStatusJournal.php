<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;

final readonly class ValidatedOperationStatusJournal
{
    /**
     * @param list<JournalRecord> $records
     * @param list<OperationStatusJournalAttempt> $attempts
     */
    public function __construct(
        public JournalOperation $operation,
        public LifecycleState $state,
        public array $records,
        public array $attempts,
        public ?DateTimeImmutable $retryAt,
    ) {}

    public function lastAttempt(): ?OperationStatusJournalAttempt
    {
        return $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)];
    }
}
