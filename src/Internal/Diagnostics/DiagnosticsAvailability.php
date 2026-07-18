<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

enum DiagnosticsAvailability: string
{
    case Available = 'available';
    case Purged = 'purged';
    case NotApplicable = 'not_applicable';
}
