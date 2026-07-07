<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Journal;

use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\Exception\LifecycleTransitionException;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LifecycleStateMachineTest extends TestCase
{
    #[DataProvider('allowedTransitions')]
    public function testAllowsExpectedTransitions(
        ?LifecycleState $current,
        JournalEvent $event,
        LifecycleState $expected,
    ): void {
        self::assertSame($expected, new LifecycleStateMachine()->next($current, $event));
    }

    /**
     * @return iterable<string, array{?LifecycleState, JournalEvent, LifecycleState}>
     */
    public static function allowedTransitions(): iterable
    {
        yield 'initial received' => [null, JournalEvent::OperationReceived, LifecycleState::Received];
        yield 'received accepted' => [
            LifecycleState::Received,
            JournalEvent::OperationAccepted,
            LifecycleState::Accepted,
        ];
        yield 'received started' => [LifecycleState::Received, JournalEvent::AttemptStarted, LifecycleState::Running];
        yield 'received rejected' => [
            LifecycleState::Received,
            JournalEvent::OperationRejected,
            LifecycleState::Rejected,
        ];
        yield 'accepted started' => [LifecycleState::Accepted, JournalEvent::AttemptStarted, LifecycleState::Running];
        yield 'running succeeded' => [
            LifecycleState::Running,
            JournalEvent::AttemptSucceeded,
            LifecycleState::Finalizing,
        ];
        yield 'running rejected' => [
            LifecycleState::Running,
            JournalEvent::OperationRejected,
            LifecycleState::Rejected,
        ];
        yield 'running failed attempt' => [
            LifecycleState::Running,
            JournalEvent::AttemptFailed,
            LifecycleState::Supervising,
        ];
        yield 'supervising retry scheduled' => [
            LifecycleState::Supervising,
            JournalEvent::AttemptRetryScheduled,
            LifecycleState::RetryScheduled,
        ];
        yield 'supervising failed' => [
            LifecycleState::Supervising,
            JournalEvent::OperationFailed,
            LifecycleState::Failed,
        ];
        yield 'supervising dead lettered' => [
            LifecycleState::Supervising,
            JournalEvent::OperationDeadLettered,
            LifecycleState::DeadLettered,
        ];
        yield 'retry scheduled started' => [
            LifecycleState::RetryScheduled,
            JournalEvent::AttemptStarted,
            LifecycleState::Running,
        ];
        yield 'finalizing completed' => [
            LifecycleState::Finalizing,
            JournalEvent::OperationCompleted,
            LifecycleState::Completed,
        ];
        yield 'finalizing failed' => [
            LifecycleState::Finalizing,
            JournalEvent::OperationFailed,
            LifecycleState::Failed,
        ];
    }

    public function testRejectsNonReceivedInitialEvent(): void
    {
        $this->expectException(LifecycleTransitionException::class);

        new LifecycleStateMachine()->next(null, JournalEvent::AttemptStarted);
    }

    #[DataProvider('terminalStates')]
    public function testRejectsEventsAfterTerminalState(LifecycleState $terminal): void
    {
        $this->expectException(LifecycleTransitionException::class);

        new LifecycleStateMachine()->next($terminal, JournalEvent::AttemptStarted);
    }

    /**
     * @return iterable<string, array{LifecycleState}>
     */
    public static function terminalStates(): iterable
    {
        yield 'completed' => [LifecycleState::Completed];
        yield 'rejected' => [LifecycleState::Rejected];
        yield 'failed' => [LifecycleState::Failed];
        yield 'dead lettered' => [LifecycleState::DeadLettered];
    }

    public function testLifecycleStateIdentifiesTerminalStates(): void
    {
        self::assertTrue(LifecycleState::Completed->isTerminal());
        self::assertTrue(LifecycleState::Rejected->isTerminal());
        self::assertTrue(LifecycleState::Failed->isTerminal());
        self::assertTrue(LifecycleState::DeadLettered->isTerminal());
        self::assertFalse(LifecycleState::Running->isTerminal());
    }
}
