<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

use BlackOps\Core\Validation\Violation;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalData;
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
            $this->data($record->data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function data(JournalData $data): array
    {
        if (!$data instanceof OperationRejectedData) {
            return $this->sensitive->projectObject($data);
        }

        return [
            'reason' => [
                'category' => $data->reason->category()->value,
                'code' => $data->reason->code(),
                'violations' => array_map(static fn(Violation $violation): array => [
                    'field' => $violation->field,
                    'rule' => $violation->rule,
                    'code' => $violation->code,
                ], $data->reason->violations()),
            ],
        ];
    }
}
