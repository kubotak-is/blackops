<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum OperationalHealthStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
}
