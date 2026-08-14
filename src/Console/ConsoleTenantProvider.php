<?php

declare(strict_types=1);

namespace BlackOps\Console;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\TenantRef;

#[PublicApi]
interface ConsoleTenantProvider
{
    public function tenant(): ?TenantRef;
}
