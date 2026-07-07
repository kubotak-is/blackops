<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum LifecycleState: string
{
    case Received = 'received';
    case Accepted = 'accepted';
    case Running = 'running';
    case Supervising = 'supervising';
    case RetryScheduled = 'retry_scheduled';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case DeadLettered = 'dead_lettered';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Rejected, self::Failed, self::DeadLettered => true,
            self::Received,
            self::Accepted,
            self::Running,
            self::Supervising,
            self::RetryScheduled,
            self::Finalizing,
                => false,
        };
    }
}
