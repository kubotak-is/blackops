<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;

final readonly class ObservedJournalRecordProjector
{
    public function __construct(
        private SensitiveProjectionFilter $sensitive,
    ) {}

    public function project(JournalRecord $record): ObservedJournalRecord
    {
        return new ObservedJournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            $record->operation,
            $record->attempt,
            $this->sensitive->projectObject($record->data),
        );
    }
}
