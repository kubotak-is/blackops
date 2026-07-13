<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;

final readonly class ApplicationJournalObservations
{
    public function __construct(
        private JournalObservationPipeline $pipeline,
        private JournalObserverAggregator $observers,
    ) {}

    public function pipeline(): JournalObservationPipeline
    {
        return $this->pipeline;
    }

    public function flush(): void
    {
        $this->observers->flush();
    }
}
