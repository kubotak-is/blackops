<?php

declare(strict_types=1);

namespace BlackOps\Scheduling;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;

#[PublicApi]
interface ScheduledTenantProvider
{
    public function tenant(ScheduleContext $context): ?TenantRef;
}
