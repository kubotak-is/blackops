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
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Validation\Violation;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyRecovery;
use BlackOps\Internal\Idempotency\IdempotencyReplayFailure;
use BlackOps\Internal\Idempotency\IdempotencyResultSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
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
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use Closure;
use LogicException;
use Throwable;

/**
 * Owns the complete inline lifecycle so state, canonical records, and transaction ordering stay synchronized.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
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
        private EphemeralOutcomeRuntimeValidator $ephemeralOutcomes = new EphemeralOutcomeRuntimeValidator(),
        private ?AuthorizationEvaluator $authorization = null,
        private ?OperationTransactionCoordinator $transactions = null,
        ?OperationMetadataResolver $metadataResolver = null,
        private ?IdempotencyStore $idempotency = null,
        private IdempotencyScopeHasher $idempotencyScopes = new IdempotencyScopeHasher(),
        private OperationValueFingerprinter $idempotencyFingerprints = new OperationValueFingerprinter(),
        private ?RetentionPeriod $idempotencyRetention = null,
        private ?IdempotencyRecovery $idempotencyRecovery = null,
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
        ?IdempotencyKey $idempotencyKey = null,
    ): OperationResult {
        if ($idempotencyKey !== null && $actorContext?->authorization() === null) {
            return OperationResult::rejected(RejectionReason::businessRule('idempotency_requires_authenticated_actor'));
        }
        if ($idempotencyKey !== null && $this->idempotency === null) {
            throw new LogicException('Idempotency store is unavailable.');
        }

        $metadata = $this->metadata($definition);

        if ($metadata->strategy !== Inline::class) {
            throw new LogicException('Inline dispatcher requires the Inline execution strategy.');
        }

        if (!$value instanceof $metadata->value) {
            throw new LogicException('Operation value does not match registered metadata.');
        }

        $sequence = new InlineSequence();
        $receivedEnvelope = new OperationEnvelope(
            $definition,
            $value,
            $this->contexts->receive(actorContext: $actorContext, idempotencyKey: $idempotencyKey),
            new Inline(),
        );
        $authorizationRejection = null;
        $authorizationPreEvaluated = false;
        $claim = null;
        if ($idempotencyKey !== null) {
            $authorizationRejection = $this->authorizationRejection($metadata, $receivedEnvelope);
            $authorizationPreEvaluated = true;
            if ($authorizationRejection === null) {
                $claim = $this->claimBeforeLifecycle($metadata, $receivedEnvelope, $idempotencyKey);
                if ($claim->status() !== IdempotencyClaimStatus::Claimed) {
                    return $this->duplicateResult($claim);
                }
            }
        }
        $processing = $claim?->record();
        if ($processing !== null && !$processing instanceof ProcessingRecord) {
            throw new LogicException('Idempotency claim did not return a processing record.');
        }
        $envelope = $receivedEnvelope;
        $state = null;
        $primaryFailure = null;

        try {
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

            return $this->scope->run(
                $envelope,
                function () use (
                    $metadata,
                    $envelope,
                    $receivedEnvelope,
                    &$state,
                    $sequence,
                    &$primaryFailure,
                    $idempotencyKey,
                    $processing,
                    $authorizationRejection,
                    $authorizationPreEvaluated,
                ): OperationResult {
                    return $this->executeAttempt(
                        $metadata,
                        $envelope,
                        $receivedEnvelope,
                        $state,
                        $sequence,
                        $primaryFailure,
                        $idempotencyKey,
                        $processing,
                        $authorizationRejection,
                        $authorizationPreEvaluated,
                    );
                },
                $metadata->typeId,
            );
        } catch (Throwable $failure) {
            $primaryFailure ??= $failure;
            if ($processing instanceof ProcessingRecord && $state !== null && $this->idempotency !== null) {
                $safeFailure = IdempotencyResultSnapshot::internalFailure($envelope->id());
                $terminal = new TerminalRecord(
                    $processing->scope(),
                    $processing->key(),
                    $processing->fingerprint(),
                    $envelope->id(),
                    $processing->strategy(),
                    $processing->createdAt(),
                    $processing->expiresAt(),
                    null,
                    $safeFailure,
                );
                $this->idempotency->terminalize($envelope->id(), $terminal);
            }
            $journalRecorded = false;
            $recordingFailure = null;

            if (
                in_array($state, [LifecycleState::Running, LifecycleState::Finalizing], strict: true)
                && $envelope->context()->attempt() !== null
            ) {
                $recordingFailure = $this->recordAttemptFailure(
                    $state,
                    $metadata,
                    $envelope,
                    $sequence,
                    $primaryFailure,
                );
                $journalRecorded = $recordingFailure === null;
            }

            throw new OperationExecutionFailed(
                $envelope,
                $metadata->typeId,
                $primaryFailure,
                $journalRecorded,
                $recordingFailure,
            );
        }
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     * @mago-expect lint:halstead
     */
    private function executeAttempt(
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        OperationEnvelope $receivedEnvelope,
        LifecycleState &$state,
        InlineSequence $sequence,
        ?Throwable &$primaryFailure,
        ?IdempotencyKey $idempotencyKey,
        ?ProcessingRecord $processing,
        ?RejectionReason $preAuthorizationRejection,
        bool $authorizationPreEvaluated,
    ): OperationResult {
        $authorizationResult = $this->authorize(
            $metadata,
            $envelope,
            $state,
            $sequence,
            $preAuthorizationRejection,
            $authorizationPreEvaluated,
        );
        if ($authorizationResult !== null) {
            return $authorizationResult;
        }

        $handler = $this->handlers->resolve($metadata->handler);
        $terminalRecords = [];
        $invoke = function () use (
            $metadata,
            $handler,
            $envelope,
            $receivedEnvelope,
            &$primaryFailure,
        ): OperationResult {
            try {
                return $this->invoke($metadata, $handler, $envelope, $receivedEnvelope->context());
            } catch (Throwable $failure) {
                $primaryFailure ??= $failure;

                throw $failure;
            }
        };
        $result = $this->executeInvocation(
            $metadata,
            $invoke,
            $envelope,
            $sequence,
            $state,
            $terminalRecords,
            $primaryFailure,
        );

        if ($result->isCompleted()) {
            if ($terminalRecords !== []) {
                foreach ($terminalRecords as $record) {
                    $this->observations->observe($record);
                }

                return $this->terminalizeIdempotency($result, $processing, $idempotencyKey, $envelope->id());
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

            return $this->terminalizeIdempotency($result, $processing, $idempotencyKey, $envelope->id());
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

        return $this->terminalizeIdempotency($rejected, $processing, $idempotencyKey, $envelope->id());
    }

    private function claimBeforeLifecycle(
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        IdempotencyKey $key,
    ): \BlackOps\Internal\Idempotency\IdempotencyClaimResult {
        $actor = $envelope->context()->actorContext()?->authorization() ?? throw new LogicException(
            'Idempotency actor is unavailable.',
        );
        $store = $this->idempotency ?? throw new LogicException('Idempotency store is unavailable.');
        $retention = $this->idempotencyRetention ?? throw new LogicException('Idempotency retention is unavailable.');
        return $store->claim(
            $this->idempotencyScopes->hash($metadata->typeId, $actor, $key),
            $key->hash(),
            $this->idempotencyFingerprints->fingerprint($metadata->typeId, $envelope->value()),
            $envelope->id(),
            new Inline(),
            $envelope->context()->receivedAt(),
            $retention->expiresAt($envelope->context()->receivedAt()),
        );
    }

    private function duplicateResult(\BlackOps\Internal\Idempotency\IdempotencyClaimResult $claim): OperationResult
    {
        if ($claim->status() === IdempotencyClaimStatus::ExistingConflict) {
            return OperationResult::rejected(RejectionReason::conflict('idempotency_conflict'));
        }
        $record = $claim->record();
        if ($record instanceof ProcessingRecord) {
            if ($this->idempotencyRecovery !== null) {
                $recovered = $this->idempotencyRecovery->inline($record);
                if ($recovered !== null) {
                    return $recovered;
                }
            }
            return OperationResult::rejected(RejectionReason::conflict('idempotency_in_progress'));
        }
        $snapshot = $record->result();
        if ($snapshot?->isInternalFailure() === true) {
            throw new IdempotencyReplayFailure($snapshot->internalFailureOperationId());
        }

        return $snapshot?->result()->asReplayed() ?? OperationResult::rejected(RejectionReason::conflict(
            'idempotency_expired',
        ));
    }

    private function terminalizeIdempotency(
        OperationResult $result,
        ?ProcessingRecord $processing,
        ?IdempotencyKey $key,
        OperationId $operationId,
    ): OperationResult {
        if ($processing === null || $key === null || $this->idempotency === null) {
            return $result;
        }

        $replay = $result->isCompleted()
            ? OperationResult::completed($result->outcome(), $operationId)
            : OperationResult::rejected($result->rejectionReason(), $operationId);
        $terminal = new TerminalRecord(
            $processing->scope(),
            $processing->key(),
            $processing->fingerprint(),
            $operationId,
            $processing->strategy(),
            $processing->createdAt(),
            $processing->expiresAt(),
            null,
            new IdempotencyResultSnapshot($replay),
        );
        if (!$this->idempotency->terminalize($operationId, $terminal)) {
            throw new LogicException('Idempotency terminalization failed.');
        }

        return $replay;
    }

    /**
     * @param Closure(): OperationResult $invoke
     * @param list<JournalRecord> $terminalRecords
     *
     * @mago-expect lint:excessive-parameter-list
     */
    private function executeInvocation(
        OperationMetadata $metadata,
        Closure $invoke,
        OperationEnvelope $envelope,
        InlineSequence $sequence,
        LifecycleState $state,
        array &$terminalRecords,
        ?Throwable &$primaryFailure,
    ): OperationResult {
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
            &$primaryFailure,
        ): void {
            try {
                $terminalRecords = $this->completeCanonical($state, $metadata, $envelope, $sequence, $result);
            } catch (Throwable $failure) {
                $primaryFailure ??= $failure;

                throw $failure;
            }
        });
    }

    private function recordAttemptFailure(
        LifecycleState $state,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        InlineSequence $sequence,
        Throwable $failure,
    ): ?Throwable {
        try {
            if ($state === LifecycleState::Finalizing) {
                $this->lifecycle->next($state, JournalEvent::OperationFailed);
                $this->journal->append($this->journalRecords->terminal()->operationFailed(
                    $envelope,
                    $metadata,
                    $sequence->next(),
                    new OperationFailedData($failure::class, $failure->getMessage(), false),
                ));

                return null;
            }

            $supervising = $this->lifecycle->next($state, JournalEvent::AttemptFailed);
            $attemptFailed = $this->journalRecords->attemptFailed(
                $envelope,
                $metadata,
                $sequence->next(),
                new AttemptFailedData($failure::class, $failure->getMessage(), false),
            );
            $this->journal->append($attemptFailed);

            $this->lifecycle->next($supervising, JournalEvent::OperationFailed);
            $operationFailed = $this->journalRecords->terminal()->operationFailed(
                $envelope,
                $metadata,
                $sequence->next(),
                new OperationFailedData($failure::class, $failure->getMessage(), false),
            );
            $this->journal->append($operationFailed);

            return null;
        } catch (Throwable $recordingFailure) {
            return $recordingFailure;
        }
    }

    private function invoke(
        OperationMetadata $metadata,
        object $handler,
        OperationEnvelope $envelope,
        \BlackOps\Core\ExecutionContext $context,
    ): OperationResult {
        try {
            $result = $this->invoker->invoke($metadata, $handler, $envelope, $context);
            $this->ephemeralOutcomes->validate($metadata, $result);

            return $result;
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

    /** @mago-expect lint:excessive-parameter-list */
    /** @mago-expect lint:no-boolean-flag-parameter */
    private function authorize(
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        LifecycleState $state,
        InlineSequence $sequence,
        ?RejectionReason $preAuthorizationRejection = null,
        bool $authorizationPreEvaluated = false,
    ): ?OperationResult {
        if ($metadata->authorizationPolicy === null) {
            return null;
        }

        if ($this->authorization === null) {
            throw new LogicException('Authorization evaluator is unavailable.');
        }

        $rejection = $authorizationPreEvaluated
            ? $preAuthorizationRejection
            : $this->authorization->evaluate($metadata, $envelope);
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

        return OperationResult::rejected(
            $rejection,
            $envelope->context()->idempotencyKeyHash() === null ? $envelope->id() : null,
        );
    }

    private function authorizationRejection(OperationMetadata $metadata, OperationEnvelope $envelope): ?RejectionReason
    {
        if ($metadata->authorizationPolicy === null) {
            return null;
        }
        if ($this->authorization === null) {
            throw new LogicException('Authorization evaluator is unavailable.');
        }

        return $this->authorization->evaluate($metadata, $envelope);
    }
}
