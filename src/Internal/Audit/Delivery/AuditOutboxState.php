<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

enum AuditOutboxState: string
{
    case Pending = 'pending';
    case Leased = 'leased';
    case Relayed = 'relayed';
    case Poisoned = 'poisoned';
}
