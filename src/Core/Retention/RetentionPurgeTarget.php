<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum RetentionPurgeTarget: string
{
    case TransportPayload = 'transport_payload';
    case Journal = 'journal';
    case Outcome = 'outcome';
    case DeadLetter = 'dead_letter';
}
