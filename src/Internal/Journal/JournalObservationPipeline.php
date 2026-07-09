<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Journal\JournalRecord;

final readonly class JournalObservationPipeline
{
    public function __construct(
        private ObservedJournalRecordProjector $records,
        private JournalObserverAggregator $observers,
    ) {}

    public function observe(JournalRecord $record): void
    {
        if ($this->observers->isEmpty()) {
            return;
        }

        $this->observers->observe($this->records->project($record));
    }
}
