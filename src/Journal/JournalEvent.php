<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum JournalEvent: string
{
    case OperationReceived = 'operation.received';
    case OperationAccepted = 'operation.accepted';
    case AttemptStarted = 'attempt.started';
    case AttemptSucceeded = 'attempt.succeeded';
    case AttemptFailed = 'attempt.failed';
    case AttemptRetryScheduled = 'attempt.retry_scheduled';
    case OperationCompleted = 'operation.completed';
    case OperationRejected = 'operation.rejected';
    case OperationFailed = 'operation.failed';
    case OperationDeadLettered = 'operation.dead_lettered';
}
