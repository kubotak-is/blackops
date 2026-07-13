<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Journal\Exception\LifecycleTransitionException;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;

readonly class LifecycleStateMachine
{
    /**
     * @var array<string, array<string, string>>
     */
    private const TRANSITIONS = [
        'initial' => [
            'operation.received' => 'received',
            'operation.rejected' => 'rejected',
        ],
        'received' => [
            'operation.accepted' => 'accepted',
            'attempt.started' => 'running',
            'operation.rejected' => 'rejected',
        ],
        'accepted' => [
            'attempt.started' => 'running',
        ],
        'running' => [
            'attempt.succeeded' => 'finalizing',
            'operation.rejected' => 'rejected',
            'attempt.failed' => 'supervising',
        ],
        'supervising' => [
            'attempt.retry_scheduled' => 'retry_scheduled',
            'operation.failed' => 'failed',
            'operation.dead_lettered' => 'dead_lettered',
        ],
        'retry_scheduled' => [
            'attempt.started' => 'running',
        ],
        'finalizing' => [
            'operation.completed' => 'completed',
            'operation.failed' => 'failed',
        ],
    ];

    public function next(?LifecycleState $current, JournalEvent $event): LifecycleState
    {
        $state = $current->value ?? 'initial';
        $next = self::TRANSITIONS[$state][$event->value] ?? null;

        if ($next === null) {
            throw LifecycleTransitionException::invalid($current, $event);
        }

        return LifecycleState::from($next);
    }
}
