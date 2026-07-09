<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

final readonly class JournalObservationResult
{
    /**
     * @param list<string> $successfulObservers
     * @param list<JournalObserverFailure> $failures
     */
    public function __construct(
        private array $successfulObservers,
        private array $failures,
    ) {}

    /**
     * @return list<string>
     */
    public function successfulObservers(): array
    {
        return $this->successfulObservers;
    }

    /**
     * @return list<JournalObserverFailure>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
