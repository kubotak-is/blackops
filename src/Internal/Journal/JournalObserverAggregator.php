<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use Closure;

final readonly class JournalObserverAggregator
{
    /**
     * @param list<JournalObserverBinding> $observers
     */
    public function __construct(
        private array $observers,
    ) {}

    public function observe(ObservedJournalRecord $record): JournalObservationResult
    {
        return $this->dispatch(static function (JournalObserverBinding $binding) use ($record): void {
            $binding->observer()->observe($record);
        });
    }

    public function flush(): JournalObservationResult
    {
        $successfulObservers = [];
        $failures = [];

        foreach ($this->observers as $binding) {
            $observer = $binding->observer();

            if (!$observer instanceof FlushableJournalObserver) {
                continue;
            }

            try {
                $observer->flush();
                $successfulObservers[] = $binding->name();
            } catch (JournalObservationFailed $exception) {
                $failures[] = new JournalObserverFailure($binding->name(), $binding->policy(), $exception);
            }
        }

        $this->throwWhenBlockingFailureExists($failures);

        return new JournalObservationResult($successfulObservers, $failures);
    }

    /**
     * @param Closure(JournalObserverBinding): void $dispatch
     */
    private function dispatch(Closure $dispatch): JournalObservationResult
    {
        $successfulObservers = [];
        $failures = [];

        foreach ($this->observers as $binding) {
            try {
                $dispatch($binding);
                $successfulObservers[] = $binding->name();
            } catch (JournalObservationFailed $exception) {
                $failures[] = new JournalObserverFailure($binding->name(), $binding->policy(), $exception);
            }
        }

        $this->throwWhenBlockingFailureExists($failures);

        return new JournalObservationResult($successfulObservers, $failures);
    }

    /**
     * @param list<JournalObserverFailure> $failures
     */
    private function throwWhenBlockingFailureExists(array $failures): void
    {
        foreach ($failures as $failure) {
            if ($failure->blocksOperation()) {
                throw new JournalObservationFailed(
                    'Required journal observer delivery failed.',
                    0,
                    $failure->exception(),
                );
            }
        }
    }
}
