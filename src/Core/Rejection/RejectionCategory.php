<?php

declare(strict_types=1);

namespace BlackOps\Core\Rejection;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum RejectionCategory: string
{
    case Validation = 'validation';
    case Unauthorized = 'unauthorized';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case BusinessRule = 'business_rule';
}
