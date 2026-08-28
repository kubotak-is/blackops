<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditEvidenceClassification: string
{
    case SafeCode = 'safe_code';
    case OpaqueId = 'opaque_id';
}
