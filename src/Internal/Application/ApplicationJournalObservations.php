<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Replay\ObserverReplayTargetRegistry;

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

    public function replayTargets(): ObserverReplayTargetRegistry
    {
        return new ObserverReplayTargetRegistry($this->observers->bindings());
    }
}
