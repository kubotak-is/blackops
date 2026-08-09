<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum OperationalHealthKind: string
{
    case Liveness = 'liveness';
    case Readiness = 'readiness';
}
