<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\JournalDeliveryPolicy;

final readonly class JournalObserverFailure
{
    public function __construct(
        private string $observerName,
        private JournalDeliveryPolicy $policy,
        private JournalObservationFailed $exception,
    ) {}

    public function observerName(): string
    {
        return $this->observerName;
    }

    public function policy(): JournalDeliveryPolicy
    {
        return $this->policy;
    }

    public function exception(): JournalObservationFailed
    {
        return $this->exception;
    }

    public function blocksOperation(): bool
    {
        return $this->policy !== JournalDeliveryPolicy::BestEffort;
    }
}
