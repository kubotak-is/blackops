<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

enum AuditRelayFailure: string
{
    case TransactionInvalid = 'transaction_invalid';
    case ResourceUnavailable = 'resource_unavailable';
    case StorageUnavailable = 'storage_unavailable';
}
