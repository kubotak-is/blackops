<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
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
    ) {}

    public function accept(
        DeferredOperationMessage $message,
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
    ): DeferredAcknowledgement {
        $this->assertMatches($message, $envelope, $metadata);

        try {
            return $this->connection->transactional(function () use (
                $message,
                $envelope,
                $metadata,
            ): DeferredAcknowledgement {
                $acknowledgement = $this->sender->enqueue($message);

                $received = $this->records->operationReceived($envelope, $metadata, 1);
                $accepted = $this->records->operationAccepted($envelope, $metadata, 2);

                $state = $this->lifecycle->next(null, JournalEvent::OperationReceived);
                $this->lifecycle->next($state, JournalEvent::OperationAccepted);

                $this->journal->append($received);
                $this->journal->append($accepted);
                $this->sender->advanceNextSequence($message, 3);

                return $acknowledgement;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to accept deferred operation.', previous: $exception);
        }
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

        if ($metadata->definition !== $envelope->definition()::class || $metadata->strategy !== Deferred::class) {
            throw new LogicException('Deferred acceptance requires deferred operation metadata.');
        }

        if (!$envelope->strategy() instanceof Deferred) {
            throw new LogicException('Deferred acceptance requires a deferred operation envelope.');
        }
    }
}
