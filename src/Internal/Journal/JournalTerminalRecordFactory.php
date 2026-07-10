<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;

final readonly class JournalTerminalRecordFactory
{
    public function __construct(
        private JournalRecordBuilder $builder,
    ) {}

    public function operationFailed(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        OperationFailedData $data,
    ): JournalRecord {
        return $this->builder->build($envelope, $metadata, $sequence, JournalEvent::OperationFailed, $data);
    }

    public function operationDeadLettered(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        OperationDeadLetteredData $data,
    ): JournalRecord {
        return $this->builder->build($envelope, $metadata, $sequence, JournalEvent::OperationDeadLettered, $data);
    }
}
