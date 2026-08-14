<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OperationalHealthQuery
{
    public function check(OperationalHealthKind $kind): OperationalHealthReport;
}
