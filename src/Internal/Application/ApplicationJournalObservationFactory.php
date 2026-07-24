<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\LazyJsonlJournalObserver;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Logging\JsonlJournalObserver;
use InvalidArgumentException;

final readonly class ApplicationJournalObservationFactory
{
    /** @param array<string, array<array-key, mixed>> $configuration */
    public function create(array $configuration): ?ApplicationJournalObservations
    {
        $jsonl = ApplicationJournalConfiguration::fromConfiguration($configuration);
        if (!$jsonl->enabled) {
            return null;
        }

        set_error_handler(static fn(): bool => true);
        try {
            $stream = fopen($jsonl->path ?? '', mode: 'ab');
        } finally {
            restore_error_handler();
        }
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(
                'Configuration key "journal.jsonl.path" could not open the JSONL journal.',
            );
        }
        $observers = new JournalObserverAggregator([
            new JournalObserverBinding('application-jsonl', new JsonlJournalObserver($stream), $jsonl->delivery),
        ]);

        return new ApplicationJournalObservations(
            new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
                $observers,
            ),
            $observers,
        );
    }

    /** @param array<string, array<array-key, mixed>> $configuration */
    public function replayTargets(array $configuration): ?\BlackOps\Internal\Replay\ObserverReplayTargetRegistry
    {
        $jsonl = ApplicationJournalConfiguration::fromConfiguration($configuration);
        if (!$jsonl->enabled) {
            return null;
        }
        return new \BlackOps\Internal\Replay\ObserverReplayTargetRegistry([
            new JournalObserverBinding(
                'application-jsonl',
                new LazyJsonlJournalObserver($jsonl->path ?? ''),
                $jsonl->delivery,
            ),
        ]);
    }
}
