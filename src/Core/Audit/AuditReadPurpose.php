<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditReadPurpose: string
{
    case Status = 'status';
    case Journal = 'journal';
    case Outcome = 'outcome';
    case Diagnostics = 'diagnostics';
}
