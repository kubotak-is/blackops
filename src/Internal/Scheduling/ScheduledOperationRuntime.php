<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use LogicException;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

final readonly class ScheduledOperationRuntime
{
    public function __construct(
        private ScheduledOperationEnvelopeFactory $envelopes,
        private ScheduledInlineDispatcher $inline,
        private ScheduledDeferredAcceptor $deferred,
        private OperationCodec $codec,
        private ClockInterface $clock = new PostgreSqlSystemClock(),
        private ?PostgreSqlScheduledOccurrenceLifecycle $scheduledOccurrences = null,
    ) {}

    public function invokeInline(
        OperationMetadata $metadata,
        Operation $definition,
        ScheduleOccurrence $occurrence,
    ): OperationResult {
        $envelope = $this->envelope($metadata, $definition, $occurrence, new Inline());

        return $this->inline->dispatchScheduled($envelope, $metadata);
    }

    public function acceptDeferred(
        OperationMetadata $metadata,
        Operation $definition,
        ScheduleOccurrence $occurrence,
    ): DeferredAcknowledgement|OperationResult {
        $envelope = $this->envelope($metadata, $definition, $occurrence, new Deferred());
        $encoded = $this->codec->encode($metadata, $envelope->value(), $envelope->context());
        $message = new DeferredOperationMessage(
            $envelope->id(),
            $encoded->operationType(),
            $encoded->schemaVersion(),
            $encoded->encodedPayload(),
            $encoded->encodedContext(),
            $occurrence->scheduledAt,
            $envelope->context()->tenant(),
            $envelope->context()->actorContext()?->origin(),
        );

        return $this->deferred->accept($message, $envelope, $metadata);
    }

    public function invoke(
        OperationMetadata $metadata,
        Operation $definition,
        ScheduleOccurrence $occurrence,
    ): DeferredAcknowledgement|OperationResult {
        return match ($metadata->strategy) {
            Inline::class => $this->invokeInline($metadata, $definition, $occurrence),
            Deferred::class => $this->acceptDeferred($metadata, $definition, $occurrence),
            default => throw new LogicException('Unsupported scheduled operation execution strategy.'),
        };
    }

    private function envelope(
        OperationMetadata $metadata,
        Operation $definition,
        ScheduleOccurrence $occurrence,
        Inline|Deferred $strategy,
    ): \BlackOps\Core\OperationEnvelope {
        try {
            $value = $this->envelopes->value($metadata);
        } catch (Throwable $failure) {
            $this->failOccurrence($occurrence, 'scheduled_value_construction_failed', $failure);
            throw $failure;
        }

        try {
            return $this->envelopes->create($metadata, $definition, $value, $occurrence, $strategy);
        } catch (Throwable $failure) {
            $this->failOccurrence($occurrence, 'scheduled_actor_resolution_failed', $failure);
            throw $failure;
        }
    }

    private function failOccurrence(ScheduleOccurrence $occurrence, string $category, Throwable $failure): void
    {
        if ($this->scheduledOccurrences === null || $occurrence->operationId === null) {
            return;
        }

        try {
            $this->scheduledOccurrences->transition(
                $occurrence->operationId,
                'claimed',
                'failed',
                $category,
                $this->clock->now(),
            );
        } catch (Throwable $transitionFailure) {
            throw new RuntimeException(
                'Scheduled invocation failure could not be recorded safely.',
                previous: $failure,
            );
        }
    }
}
