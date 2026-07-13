<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Journal\InlineSequence;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Validation\OperationValueValidator;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use Closure;
use LogicException;

final readonly class InlineDispatcher implements Dispatcher, ValidationRejectionRecorder
{
    private JournalObservationPipeline $observations;

    private OperationValueValidator $validator;

    public function __construct(
        private OperationRegistry $registry,
        private ExecutionContextFactory $contexts,
        private HandlerResolver $handlers,
        private JournalRecordFactory $journalRecords,
        private CanonicalJournalWriter $journal,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        ?JournalObservationPipeline $observations = null,
        private ExecutionScopeProvider $scope = new ExecutionScopeProvider(),
        private HandlerInvoker $invoker = new HandlerInvoker(),
    ) {
        $this->validator = new OperationValueValidator();
        $this->observations = $observations ?? new JournalObservationPipeline(
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            new JournalObserverAggregator([]),
        );
    }

    /**
     * @return list<Violation>
     */
    public function validate(OperationValue $value): array
    {
        return $this->validator->validate($value);
    }

    public function dispatch(Operation $definition, OperationValue $value): OperationResult
    {
        $metadata = $this->registry->findByDefinition($definition::class) ?? throw new LogicException(
            'Operation definition is not registered.',
        );

        if ($metadata->strategy !== Inline::class) {
            throw new LogicException('Inline dispatcher requires the Inline execution strategy.');
        }

        if (!$value instanceof $metadata->value) {
            throw new LogicException('Operation value does not match registered metadata.');
        }

        $sequence = new InlineSequence();
        $state = null;
        $receivedEnvelope = new OperationEnvelope($definition, $value, $this->contexts->receive(), new Inline());
        $state = $this->appendLifecycleRecord(
            $state,
            JournalEvent::OperationReceived,
            fn(): JournalRecord => $this->journalRecords->operationReceived(
                $receivedEnvelope,
                $metadata,
                $sequence->next(),
            ),
        );

        $envelope = new OperationEnvelope(
            $definition,
            $value,
            $this->contexts->startAttempt($receivedEnvelope->context(), 1),
            new Inline(),
        );
        $state = $this->appendLifecycleRecord(
            $state,
            JournalEvent::AttemptStarted,
            fn(): JournalRecord => $this->journalRecords->attemptStarted($envelope, $metadata, $sequence->next()),
        );
        $handler = $this->handlers->resolve($metadata->handler);
        $result = $this->scope->run(
            $envelope,
            fn(): OperationResult => $this->invoker->invoke(
                $metadata,
                $handler,
                $envelope,
                $receivedEnvelope->context(),
            ),
            $metadata->typeId,
        );

        if ($result->isCompleted()) {
            $state = $this->appendLifecycleRecord(
                $state,
                JournalEvent::AttemptSucceeded,
                fn(): JournalRecord => $this->journalRecords->attemptSucceeded($envelope, $metadata, $sequence->next()),
            );
            $this->appendLifecycleRecord(
                $state,
                JournalEvent::OperationCompleted,
                fn(): JournalRecord => $this->journalRecords->operationCompleted(
                    $envelope,
                    $metadata,
                    $sequence->next(),
                    $result->outcome(),
                ),
            );

            return $result;
        }

        $this->appendLifecycleRecord(
            $state,
            JournalEvent::OperationRejected,
            fn(): JournalRecord => $this->journalRecords->operationRejected(
                $envelope,
                $metadata,
                $sequence->next(),
                $result->rejectionReason(),
            ),
        );

        return $result;
    }

    /**
     * @param list<Violation> $violations
     */
    public function rejectBinding(Operation $definition, array $violations): OperationId
    {
        $metadata = $this->metadata($definition);
        $context = $this->contexts->receive();
        $reason = RejectionReason::validation('validation.failed', $violations);
        $this->appendLifecycleRecord(
            null,
            JournalEvent::OperationRejected,
            fn(): JournalRecord => $this->journalRecords->operationRejectedBeforeBinding(
                $definition,
                $context,
                $metadata,
                1,
                $reason,
            ),
        );

        return $context->operationId();
    }

    /**
     * @param list<Violation> $violations
     */
    public function rejectValue(Operation $definition, OperationValue $value, array $violations): OperationId
    {
        $metadata = $this->metadata($definition);
        $this->assertValueMatches($metadata, $value);
        $context = $this->contexts->receive();
        $envelope = new OperationEnvelope($definition, $value, $context, $this->strategy($metadata));
        $state = $this->appendLifecycleRecord(
            null,
            JournalEvent::OperationReceived,
            fn(): JournalRecord => $this->journalRecords->operationReceived($envelope, $metadata, 1),
        );
        $reason = RejectionReason::validation('validation.failed', $violations);
        $this->appendLifecycleRecord(
            $state,
            JournalEvent::OperationRejected,
            fn(): JournalRecord => $this->journalRecords->operationRejected($envelope, $metadata, 2, $reason),
        );

        return $context->operationId();
    }

    /**
     * @param Closure(): JournalRecord $createRecord
     */
    private function appendLifecycleRecord(
        ?LifecycleState $state,
        JournalEvent $event,
        Closure $createRecord,
    ): LifecycleState {
        $next = $this->lifecycle->next($state, $event);
        $record = $createRecord();
        $this->journal->append($record);
        $this->observe($record);

        return $next;
    }

    private function observe(JournalRecord $record): void
    {
        $this->observations->observe($record);
    }

    private function metadata(Operation $definition): OperationMetadata
    {
        return (
            $this->registry->findByDefinition($definition::class) ?? throw new LogicException(
                'Operation definition is not registered.',
            )
        );
    }

    private function assertValueMatches(OperationMetadata $metadata, OperationValue $value): void
    {
        if (!$value instanceof $metadata->value) {
            throw new LogicException('Operation value does not match registered metadata.');
        }
    }

    private function strategy(OperationMetadata $metadata): ExecutionStrategy
    {
        return match ($metadata->strategy) {
            Inline::class => new Inline(),
            Deferred::class => new Deferred(),
            default => throw new LogicException('Unsupported execution strategy.'),
        };
    }
}
