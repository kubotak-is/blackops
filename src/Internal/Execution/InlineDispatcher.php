<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Execution\Dispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Journal\InlineSequence;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use Closure;
use LogicException;

final readonly class InlineDispatcher implements Dispatcher
{
    public function __construct(
        private OperationRegistry $registry,
        private ExecutionContextFactory $contexts,
        private HandlerResolver $handlers,
        private JournalRecordFactory $journalRecords,
        private CanonicalJournalWriter $journal,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
    ) {}

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
        $result = $handler->handle($envelope);

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

        return $next;
    }
}
