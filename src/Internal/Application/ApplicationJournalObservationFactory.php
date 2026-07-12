<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Logging\JsonlJournalObserver;
use InvalidArgumentException;

final readonly class ApplicationJournalObservationFactory
{
    /** @param array<string, array<array-key, mixed>> $configuration */
    public function create(array $configuration): ?JournalObservationPipeline
    {
        $jsonl = ApplicationJournalConfiguration::fromConfiguration($configuration);
        if (!$jsonl->enabled) {
            return null;
        }

        set_error_handler(static fn(): bool => true);
        try {
            $stream = fopen(filename: $jsonl->path ?? '', mode: 'ab');
        } finally {
            restore_error_handler();
        }
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(
                'Configuration key "journal.jsonl.path" could not open the JSONL journal.',
            );
        }

        return new JournalObservationPipeline(
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            new JournalObserverAggregator([
                new JournalObserverBinding('application-jsonl', new JsonlJournalObserver($stream), $jsonl->delivery),
            ]),
        );
    }
}
