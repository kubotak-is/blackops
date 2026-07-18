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
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use Doctrine\DBAL\Connection;
use LogicException;
use Throwable;

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
    ) {}

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
                        $received = $this->records->operationReceived($envelope, $metadata, 1);
                        $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                        $this->journal->append($received);
                        $authorizationRejection = $this->authorize($metadata, $envelope);

                        if ($authorizationRejection !== null) {
                            $this->lifecycle->next($state, JournalEvent::OperationRejected);
                            $this->journal->append($this->records->operationRejected(
                                $envelope,
                                $metadata,
                                2,
                                $authorizationRejection,
                            ));

                            return OperationResult::rejected($authorizationRejection, $envelope->id());
                        }

                        $acknowledgement = $this->sender->enqueue($message);
                        $accepted = $this->records->operationAccepted($envelope, $metadata, 2);
                        $this->lifecycle->next($state, JournalEvent::OperationAccepted);
                        $this->journal->append($accepted);
                        $this->sender->advanceNextSequence($message, 3);

                        return $acknowledgement;
                    } catch (Throwable $failure) {
                        $failures->capture($failure);

                        throw $failure;
                    }
                }),
                $metadata->typeId,
            );
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
