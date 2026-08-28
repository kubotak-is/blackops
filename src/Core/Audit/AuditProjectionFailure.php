<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditProjectionFailure: string
{
    case ProjectionInvalid = 'projection_invalid';
}
