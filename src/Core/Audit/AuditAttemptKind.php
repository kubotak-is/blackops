<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditAttemptKind: string
{
    case Initial = 'initial';
    case Retry = 'retry';
    case Replay = 'replay';
}
