<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditSourceOwner: string
{
    case Framework = 'framework';
    case Application = 'application';
}
