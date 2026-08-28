<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditPolicyDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case NotApplicable = 'not_applicable';
}
