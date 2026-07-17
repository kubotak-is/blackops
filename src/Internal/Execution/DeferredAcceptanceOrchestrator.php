<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Exception\DeferredTransportException;
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
use BlackOps\Journal\JournalEvent;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use Doctrine\DBAL\Connection;
use LogicException;
use Throwable;
use WeakMap;

final readonly class DeferredAcceptanceOrchestrator
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSender $sender,
        private CanonicalJournalWriter $journal,
        private JournalRecordFactory $records,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        private ?AuthorizationEvaluator $authorization = null,
    ) {}

    public function accept(
        DeferredOperationMessage $message,
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
    ): DeferredAcknowledgement|OperationResult {
        $this->assertMatches($message, $envelope, $metadata);
        /** @var WeakMap<Throwable, bool> $authorizationFailures */
        $authorizationFailures = new WeakMap();

        try {
            return $this->connection->transactional(function () use (
                $message,
                $envelope,
                $metadata,
                $authorizationFailures,
            ): DeferredAcknowledgement|OperationResult {
                $received = $this->records->operationReceived($envelope, $metadata, 1);
                $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                $this->journal->append($received);

                try {
                    $authorizationRejection = $this->authorize($metadata, $envelope);
                } catch (Throwable $exception) {
                    $authorizationFailures[$exception] = true;
                    throw $exception;
                }

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
            });
        } catch (Throwable $exception) {
            if ($authorizationFailures[$exception] ?? false) {
                throw $exception;
            }

            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to accept deferred operation.', previous: $exception);
        }
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
