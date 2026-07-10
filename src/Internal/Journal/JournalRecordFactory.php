<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use Psr\Clock\ClockInterface;

final readonly class JournalRecordFactory
{
    private JournalRecordBuilder $builder;
    private JournalTerminalRecordFactory $terminal;

    public function __construct(IdentifierFactory $identifiers, ClockInterface $clock)
    {
        $this->builder = new JournalRecordBuilder($identifiers, $clock);
        $this->terminal = new JournalTerminalRecordFactory($this->builder);
    }

    public function terminal(): JournalTerminalRecordFactory
    {
        return $this->terminal;
    }

    public function operationReceived(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationReceived,
            new OperationReceivedData($envelope->value()),
        );
    }

    public function operationAccepted(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationAccepted,
            new EmptyJournalData(),
        );
    }

    public function attemptStarted(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::AttemptStarted,
            new EmptyJournalData(),
        );
    }

    public function attemptSucceeded(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::AttemptSucceeded,
            new EmptyJournalData(),
        );
    }

    public function attemptFailed(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        AttemptFailedData $data,
    ): JournalRecord {
        return $this->builder->build($envelope, $metadata, $sequence, JournalEvent::AttemptFailed, $data);
    }

    public function attemptRetryScheduled(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        AttemptRetryScheduledData $data,
    ): JournalRecord {
        return $this->builder->build($envelope, $metadata, $sequence, JournalEvent::AttemptRetryScheduled, $data);
    }

    public function operationCompleted(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        Outcome $outcome,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationCompleted,
            new OperationCompletedData($outcome),
        );
    }

    public function operationRejected(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        RejectionReason $reason,
    ): JournalRecord {
        return $this->builder->build(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationRejected,
            new OperationRejectedData($reason),
        );
    }
}
