<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditEvidenceType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
}
