<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditOutcomeStatus: string
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
