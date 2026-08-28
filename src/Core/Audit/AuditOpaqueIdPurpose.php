<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditOpaqueIdPurpose: string
{
    case Record = 'record';
    case Action = 'action';
    case Principal = 'principal';
    case Execution = 'execution';
    case Resource = 'resource';
    case Tenant = 'tenant';
    case Operation = 'operation';
    case Correlation = 'correlation';
    case Causation = 'causation';
    case Trace = 'trace';
    case Span = 'span';
    case Evidence = 'evidence';
}
