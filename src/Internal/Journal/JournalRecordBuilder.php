<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use LogicException;
use Psr\Clock\ClockInterface;

final readonly class JournalRecordBuilder
{
    public function __construct(
        private IdentifierFactory $identifiers,
        private ClockInterface $clock,
    ) {}

    public function build(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        int $sequence,
        JournalEvent $event,
        JournalData $data,
    ): JournalRecord {
        if ($metadata->definition !== $envelope->definition()::class) {
            throw new LogicException('Journal metadata does not match the operation envelope definition.');
        }

        if ($metadata->strategy !== $envelope->strategy()::class) {
            throw new LogicException('Journal metadata does not match the operation envelope strategy.');
        }

        return $this->buildFromContext($envelope->context(), $metadata, $sequence, $event, $data);
    }

    public function buildRejectedBeforeBinding(
        ExecutionContext $context,
        OperationMetadata $metadata,
        int $sequence,
        RejectionReason $reason,
    ): JournalRecord {
        return $this->buildFromContext(
            $context,
            $metadata,
            $sequence,
            JournalEvent::OperationRejected,
            new OperationRejectedData($reason),
        );
    }

    private function buildFromContext(
        ExecutionContext $context,
        OperationMetadata $metadata,
        int $sequence,
        JournalEvent $event,
        JournalData $data,
    ): JournalRecord {
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
                $this->strategyWireName($metadata->strategy),
                $context->correlationId(),
                $context->causationId(),
                $context->actorContext(),
            ),
            $attempt === null ? null : new JournalAttempt($attempt->id(), $attempt->number(), $attempt->startedAt()),
            $data,
        );
    }

    /** @param class-string $strategy */
    private function strategyWireName(string $strategy): string
    {
        return match ($strategy) {
            Inline::class => 'inline',
            Deferred::class => 'deferred',
            default => throw new LogicException('Unsupported journal operation strategy.'),
        };
    }
}
