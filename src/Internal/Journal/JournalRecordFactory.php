<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use LogicException;
use Psr\Clock\ClockInterface;

final readonly class JournalRecordFactory
{
    public function __construct(
        private IdentifierFactory $identifiers,
        private ClockInterface $clock,
    ) {}

    public function operationReceived(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->create(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationReceived,
            new OperationReceivedData($envelope->value()),
        );
    }

    public function attemptStarted(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->create($envelope, $metadata, $sequence, JournalEvent::AttemptStarted, new EmptyJournalData());
    }

    public function attemptSucceeded(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
    ): JournalRecord {
        return $this->create($envelope, $metadata, $sequence, JournalEvent::AttemptSucceeded, new EmptyJournalData());
    }

    public function operationCompleted(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        Outcome $outcome,
    ): JournalRecord {
        return $this->create(
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
        return $this->create(
            $envelope,
            $metadata,
            $sequence,
            JournalEvent::OperationRejected,
            new OperationRejectedData($reason),
        );
    }

    private function create(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        JournalEvent $event,
        JournalData $data,
    ): JournalRecord {
        if ($metadata->definition !== $envelope->definition()::class || $metadata->strategy !== Inline::class) {
            throw new LogicException('Journal metadata does not match the inline operation envelope.');
        }
        $context = $envelope->context();
        $attempt = $context->attempt();
        return new JournalRecord(
            $this->identifiers->newJournalRecordId(),
            1,
            $event,
            $this->clock->now(),
            $sequence,
            new JournalOperation(
                $context->operationId(),
                $metadata->typeId,
                1,
                'inline',
                $context->correlationId(),
                $context->causationId(),
            ),
            $attempt === null ? null : new JournalAttempt($attempt->id(), $attempt->number(), $attempt->startedAt()),
            $data,
        );
    }
}
