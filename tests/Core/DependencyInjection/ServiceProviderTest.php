<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\DependencyInjection;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function testContractsArePublicApi(): void
    {
        self::assertNotEmpty(new \ReflectionClass(ServiceProvider::class)->getAttributes(PublicApi::class));
        self::assertNotEmpty(new \ReflectionClass(ServiceRegistry::class)->getAttributes(PublicApi::class));
    }
}
