<?php

declare(strict_types=1);

namespace BlackOps\Core\DependencyInjection;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface ServiceProvider
{
    public function register(ServiceRegistry $services): void;
}
