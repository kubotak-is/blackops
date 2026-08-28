<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditEvidenceType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
}
