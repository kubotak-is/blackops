<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Journal\JournalDeliveryPolicy;
use BlackOps\Journal\JournalObserver;
use InvalidArgumentException;

final readonly class JournalObserverBinding
{
    public function __construct(
        private string $name,
        private JournalObserver $observer,
        private JournalDeliveryPolicy $policy = JournalDeliveryPolicy::BestEffort,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $name)) {
            throw new InvalidArgumentException('Journal observer name must be a stable identifier.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function observer(): JournalObserver
    {
        return $this->observer;
    }

    public function policy(): JournalDeliveryPolicy
    {
        return $this->policy;
    }

    public function blocksOperation(): bool
    {
        return $this->policy !== JournalDeliveryPolicy::BestEffort;
    }
}
