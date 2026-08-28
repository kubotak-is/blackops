<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditActionPhase: string
{
    case Requested = 'requested';
    case Started = 'started';
    case Committed = 'committed';
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
