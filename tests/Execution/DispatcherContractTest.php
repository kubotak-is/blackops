<?php

declare(strict_types=1);

namespace BlackOps\Tests\Execution;

use BlackOps\Core\TenantRef;
use BlackOps\Execution\Dispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DispatcherContractTest extends TestCase
{
    public function testTenantIsTrailingOptionalParameter(): void
    {
        $parameters = new ReflectionMethod(Dispatcher::class, 'dispatch')->getParameters();
        self::assertSame('tenant', $parameters[4]->getName());
        self::assertTrue($parameters[4]->isOptional());
        self::assertTrue($parameters[4]->getType()?->allowsNull());
        self::assertSame(TenantRef::class, $parameters[4]->getType()?->getName());
    }
}
