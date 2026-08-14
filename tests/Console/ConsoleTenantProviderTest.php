<?php

declare(strict_types=1);

namespace BlackOps\Tests\Console;

use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Console\ConsoleTenantProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ConsoleTenantProviderTest extends TestCase
{
    public function testTenantPortIsIndependentFromActorPort(): void
    {
        self::assertNotSame(ConsoleActorProvider::class, ConsoleTenantProvider::class);
        self::assertSame('tenant', new ReflectionMethod(ConsoleTenantProvider::class, 'tenant')->getName());
    }
}
