<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;

final readonly class CommandDiscoveryServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(CommandGreeting::class, ConfiguredCommandGreeting::class);
    }
}
