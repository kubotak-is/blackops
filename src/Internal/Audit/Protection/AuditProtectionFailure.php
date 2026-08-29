<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

enum AuditProtectionFailure: string
{
    case ProtectionFailed = 'protection_failed';
}
