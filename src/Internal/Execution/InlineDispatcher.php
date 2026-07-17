<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Exception\OperationRejectedException;
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
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Journal\InlineSequence;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Registry\OperationMetadataResolver;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Validation\OperationValueValidator;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use Closure;
use LogicException;

/**
 * Owns the complete inline lifecycle so state, canonical records, and transaction ordering stay synchronized.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class InlineDispatcher implements Dispatcher, ValidationRejectionRecorder
{
    private JournalObservationPipeline $observations;

    private OperationValueValidator $validator;

    private OperationMetadataResolver $metadataResolver;

    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        OperationRegistry $registry,
        private ExecutionContextFactory $contexts,
        private HandlerResolver $handlers,
        private JournalRecordFactory $journalRecords,
        private CanonicalJournalWriter $journal,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        ?JournalObservationPipeline $observations = null,
        private ExecutionScopeProvider $scope = new ExecutionScopeProvider(),
        private HandlerInvoker $invoker = new HandlerInvoker(),
        private ?AuthorizationEvaluator $authorization = null,
        private ?OperationTransactionCoordinator $transactions = null,
        ?OperationMetadataResolver $metadataResolver = null,
    ) {
        $this->validator = new OperationValueValidator();
        $this->metadataResolver = $metadataResolver ?? new OperationMetadataResolver($registry);
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

    /** @mago-expect lint:halstead */
    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): OperationResult {
        $metadata = $this->metadata($definition);

        if ($metadata->strategy !== Inline::class) {
            throw new LogicException('Inline dispatcher requires the Inline execution strategy.');
        }

        if (!$value instanceof $metadata->value) {
            throw new LogicException('Operation value does not match registered metadata.');
        }

        $sequence = new InlineSequence();
        $state = null;
        $receivedEnvelope = new OperationEnvelope(
            $definition,
            $value,
            $this->contexts->receive(actorContext: $actorContext),
            new Inline(),
        );
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
        $authorizationResult = $this->authorize($metadata, $envelope, $state, $sequence);
        if ($authorizationResult !== null) {
            return $authorizationResult;
        }

        $handler = $this->handlers->resolve($metadata->handler);
        $terminalRecords = [];
        $invoke = fn(): OperationResult => $this->invoke($metadata, $handler, $envelope, $receivedEnvelope->context());
        $result = $this->scope->run(
            $envelope,
            function () use ($metadata, $invoke, $envelope, $sequence, $state, &$terminalRecords): OperationResult {
                if ($metadata->transactionConnection === null) {
                    return $invoke();
                }

                if ($this->transactions === null) {
                    throw new LogicException('Operation transaction coordinator is unavailable.');
                }

                return $this->transactions->execute($metadata, $invoke, function (OperationResult $result) use (
                    $metadata,
                    $envelope,
                    $sequence,
                    $state,
                    &$terminalRecords,
                ): void {
                    $terminalRecords = $this->completeCanonical($state, $metadata, $envelope, $sequence, $result);
                });
            },
            $metadata->typeId,
        );

        if ($result->isCompleted()) {
            if ($terminalRecords !== []) {
                foreach ($terminalRecords as $record) {
                    $this->observations->observe($record);
                }

                return $result;
            }

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

        $rejected = OperationResult::rejected($result->rejectionReason(), $envelope->id());
        $this->appendLifecycleRecord(
            $state,
            JournalEvent::OperationRejected,
            fn(): JournalRecord => $this->journalRecords->operationRejected(
                $envelope,
                $metadata,
                $sequence->next(),
                $rejected->rejectionReason(),
            ),
        );

        return $rejected;
    }

    private function invoke(
        OperationMetadata $metadata,
        object $handler,
        OperationEnvelope $envelope,
        \BlackOps\Core\ExecutionContext $context,
    ): OperationResult {
        try {
            return $this->invoker->invoke($metadata, $handler, $envelope, $context);
        } catch (OperationRejectedException $rejected) {
            return OperationResult::rejected($rejected->reason(), $envelope->id());
        }
    }

    /** @return list<JournalRecord> */
    private function completeCanonical(
        LifecycleState $state,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        InlineSequence $sequence,
        OperationResult $result,
    ): array {
        $finalizing = $this->lifecycle->next($state, JournalEvent::AttemptSucceeded);
        $succeeded = $this->journalRecords->attemptSucceeded($envelope, $metadata, $sequence->next());
        $this->journal->append($succeeded);
        $this->lifecycle->next($finalizing, JournalEvent::OperationCompleted);
        $completed = $this->journalRecords->operationCompleted(
            $envelope,
            $metadata,
            $sequence->next(),
            $result->outcome(),
        );
        $this->journal->append($completed);

        return [$succeeded, $completed];
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
        $this->observations->observe($record);

        return $next;
    }

    private function metadata(Operation $definition): OperationMetadata
    {
        return (
            $this->metadataResolver->resolve($definition) ?? throw new LogicException(
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

    private function authorize(
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        LifecycleState $state,
        InlineSequence $sequence,
    ): ?OperationResult {
        if ($metadata->authorizationPolicy === null) {
            return null;
        }

        if ($this->authorization === null) {
            throw new LogicException('Authorization evaluator is unavailable.');
        }

        $rejection = $this->authorization->evaluate($metadata, $envelope);
        if ($rejection === null) {
            return null;
        }

        $this->appendLifecycleRecord(
            $state,
            JournalEvent::OperationRejected,
            fn(): JournalRecord => $this->journalRecords->operationRejected(
                $envelope,
                $metadata,
                $sequence->next(),
                $rejection,
            ),
        );

        return OperationResult::rejected($rejection, $envelope->id());
    }
}
