<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

enum AuditDeliveryFailure: string
{
    case ConfigurationInvalid = 'configuration_invalid';
    case PolicyInvalid = 'policy_invalid';
    case TransactionInvalid = 'transaction_invalid';
    case KeyUnavailable = 'key_unavailable';
    case ResourceUnavailable = 'resource_unavailable';
    case StorageUnavailable = 'storage_unavailable';
}
