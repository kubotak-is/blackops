<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum OperationStatusState: string
{
    case Accepted = 'accepted';
    case Running = 'running';
    case RetryScheduled = 'retry_scheduled';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case DeadLettered = 'dead_lettered';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Accepted, self::Running, self::RetryScheduled => false,
            self::Completed, self::Rejected, self::Failed, self::DeadLettered => true,
        };
    }
}
