<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditPolicyDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case NotApplicable = 'not_applicable';
}
