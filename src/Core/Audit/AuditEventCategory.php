<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditEventCategory: string
{
    case Business = 'business';
    case Security = 'security';
    case Access = 'access';
    case Configuration = 'configuration';
    case Control = 'control';
    case Retention = 'retention';
    case Replay = 'replay';
    case Rotation = 'rotation';
    case Outbox = 'outbox';
}
