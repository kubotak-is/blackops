<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyRecovery;
use BlackOps\Internal\Idempotency\IdempotencyReplayFailure;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use Doctrine\DBAL\Connection;
use LogicException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:excessive-parameter-list
 * @mago-expect lint:excessive-nesting
 */
final readonly class DeferredAcceptanceOrchestrator
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSender $sender,
        private CanonicalJournalWriter $journal,
        private JournalRecordFactory $records,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        private ?AuthorizationEvaluator $authorization = null,
        private ExecutionScopeProvider $scope = new ExecutionScopeProvider(),
        private ?IdempotencyStore $idempotency = null,
        private IdempotencyScopeHasher $idempotencyScopes = new IdempotencyScopeHasher(),
        private OperationValueFingerprinter $idempotencyFingerprints = new OperationValueFingerprinter(),
        private ?RetentionPeriod $idempotencyRetention = null,
        private ?IdempotencyRecovery $idempotencyRecovery = null,
    ) {}

    /** @mago-expect lint:halstead */
    public function accept(
        DeferredOperationMessage $message,
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
    ): DeferredAcknowledgement|OperationResult {
        $this->assertMatches($message, $envelope, $metadata);
        $failures = new PrimaryFailureCapture();

        try {
            return $this->scope->run(
                $envelope,
                fn(): DeferredAcknowledgement|OperationResult => $this->connection->transactional(function () use (
                    $message,
                    $envelope,
                    $metadata,
                    $failures,
                ): DeferredAcknowledgement|OperationResult {
                    try {
                        $authorizationRejection = $this->authorize($metadata, $envelope);

                        if ($authorizationRejection !== null) {
                            $received = $this->records->operationReceived($envelope, $metadata, 1);
                            $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                            $this->journal->append($received);
                            $this->lifecycle->next($state, JournalEvent::OperationRejected);
                            $this->journal->append($this->records->operationRejected(
                                $envelope,
                                $metadata,
                                2,
                                $authorizationRejection,
                            ));

                            return OperationResult::rejected(
                                $authorizationRejection,
                                $envelope->context()->idempotencyKeyHash() === null ? $envelope->id() : null,
                            );
                        }

                        $processing = null;
                        $keyHash = $envelope->context()->idempotencyKeyHash();
                        if ($keyHash !== null) {
                            $actor = $envelope->context()->actorContext()?->authorization() ?? throw new LogicException(
                                'Idempotency actor is unavailable.',
                            );
                            $store = $this->idempotency ?? throw new LogicException(
                                'Idempotency store is unavailable.',
                            );
                            $retention = $this->idempotencyRetention ?? throw new LogicException(
                                'Idempotency retention is unavailable.',
                            );
                            $scope = $this->idempotencyScopes->hash($metadata->typeId, $actor, $keyHash);
                            $claim = $store->claim(
                                $scope,
                                $keyHash,
                                $this->idempotencyFingerprints->fingerprint($metadata->typeId, $envelope->value()),
                                $envelope->id(),
                                new Deferred(),
                                $envelope->context()->receivedAt(),
                                $retention->expiresAt($envelope->context()->receivedAt()),
                            );
                            if ($claim->status() === IdempotencyClaimStatus::ExistingConflict) {
                                return OperationResult::rejected(RejectionReason::conflict('idempotency_conflict'));
                            }
                            if ($claim->status() === IdempotencyClaimStatus::ExistingSameFingerprint) {
                                $existing = $claim->record();
                                if ($existing instanceof ProcessingRecord) {
                                    if ($this->idempotencyRecovery !== null) {
                                        $recovered = $this->idempotencyRecovery->deferred($existing);
                                        if ($recovered !== null) {
                                            return $recovered;
                                        }
                                    }
                                    return OperationResult::rejected(RejectionReason::conflict(
                                        'idempotency_in_progress',
                                    ));
                                }

                                return new DeferredAcknowledgement(
                                    $existing->operationId(),
                                    $existing->acceptedAt() ?? $existing->createdAt(),
                                    true,
                                );
                            }
                            $processing = $claim->record();
                            if (!$processing instanceof ProcessingRecord) {
                                throw new LogicException('Idempotency claim did not return a processing record.');
                            }
                        }

                        $received = $this->records->operationReceived($envelope, $metadata, 1);
                        $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                        $this->journal->append($received);

                        $acknowledgement = $this->sender->enqueue($message);
                        $accepted = $this->records->operationAccepted($envelope, $metadata, 2);
                        $this->lifecycle->next($state, JournalEvent::OperationAccepted);
                        $this->journal->append($accepted);
                        $this->sender->advanceNextSequence($message, 3);

                        if ($processing !== null && $this->idempotency !== null) {
                            $terminal = new TerminalRecord(
                                $processing->scope(),
                                $processing->key(),
                                $processing->fingerprint(),
                                $processing->operationId(),
                                $processing->strategy(),
                                $processing->createdAt(),
                                $processing->expiresAt(),
                                null,
                                null,
                                $acknowledgement->acceptedAt(),
                            );
                            if (!$this->idempotency->terminalize($processing->operationId(), $terminal)) {
                                throw new LogicException('Idempotency terminalization failed.');
                            }
                        }

                        return $acknowledgement;
                    } catch (Throwable $failure) {
                        $failures->capture($failure);

                        throw $failure;
                    }
                }),
                $metadata->typeId,
            );
        } catch (IdempotencyReplayFailure $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw $this->failureBeforeAttempt($envelope, $metadata, $failures->getOr($failure));
        }
    }

    private function failureBeforeAttempt(
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
        Throwable $primaryFailure,
    ): OperationExecutionFailed {
        $recordingFailure = null;

        try {
            $this->connection->transactional(function () use ($envelope, $metadata, $primaryFailure): void {
                $received = $this->records->operationReceived($envelope, $metadata, 1);
                $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                $this->journal->append($received);
                $this->lifecycle->next($state, JournalEvent::OperationFailed);
                $this->journal->append($this->records->terminal()->operationFailed(
                    $envelope,
                    $metadata,
                    2,
                    new OperationFailedData($primaryFailure::class, $primaryFailure->getMessage(), false),
                ));
            });
        } catch (Throwable $failure) {
            $recordingFailure = $failure;
        }

        return new OperationExecutionFailed(
            $envelope,
            $metadata->typeId,
            $primaryFailure,
            $recordingFailure === null,
            $recordingFailure,
        );
    }

    private function authorize(OperationMetadata $metadata, OperationEnvelope $envelope): ?RejectionReason
    {
        if ($metadata->authorizationPolicy === null) {
            return null;
        }

        if ($this->authorization === null) {
            throw new LogicException('Authorization evaluator is unavailable.');
        }

        return $this->authorization->evaluate($metadata, $envelope);
    }

    private function assertMatches(
        DeferredOperationMessage $message,
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
    ): void {
        if (!$message->operationId()->equals($envelope->id())) {
            throw new LogicException('Deferred message operation ID must match the envelope.');
        }

        if ($message->operationType() !== $metadata->typeId) {
            throw new LogicException('Deferred message operation type must match metadata.');
        }

        $definition = $metadata->definition;

        if (!$envelope->definition() instanceof $definition || $metadata->strategy !== Deferred::class) {
            throw new LogicException('Deferred acceptance requires deferred operation metadata.');
        }

        if (!$envelope->strategy() instanceof Deferred) {
            throw new LogicException('Deferred acceptance requires a deferred operation envelope.');
        }
    }
}
