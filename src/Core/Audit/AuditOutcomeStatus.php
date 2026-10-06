<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditOutcomeStatus: string
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
