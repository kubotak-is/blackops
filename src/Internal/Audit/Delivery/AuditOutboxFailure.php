<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

enum AuditOutboxFailure: string
{
    case ConfigurationInvalid = 'configuration_invalid';
    case IntegrityInvalid = 'integrity_invalid';
    case TransitionConflict = 'transition_conflict';
    case ResourceUnavailable = 'resource_unavailable';
}
