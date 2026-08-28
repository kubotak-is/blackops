<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditReadAuthorizationDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case ResourceUnavailable = 'resource_unavailable';
}
