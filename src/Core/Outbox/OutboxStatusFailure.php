<?php

declare(strict_types=1);

namespace BlackOps\Core\Outbox;

enum OutboxStatusFailure: string
{
    case Configuration = 'configuration_invalid';
    case Storage = 'storage_unavailable';
    case Integrity = 'integrity_failed';
}
