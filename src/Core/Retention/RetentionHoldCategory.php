<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum RetentionHoldCategory: string
{
    case Legal = 'legal';
    case Security = 'security';
    case Audit = 'audit';
    case Support = 'support';
    case Other = 'other';
}
