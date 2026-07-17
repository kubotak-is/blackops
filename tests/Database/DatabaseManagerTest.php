<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Database\DatabaseManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class DatabaseManagerTest extends TestCase
{
    public function testPublicContractExposesOnlyConnectionSelection(): void
    {
        $reflection = new ReflectionClass(DatabaseManager::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertTrue($reflection->isInterface());
        self::assertNotSame([], $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['connection'],
            array_map(static fn(ReflectionMethod $method): string => $method->getName(), $methods),
        );
        self::assertSame(Connection::class, $methods[0]->getReturnType()?->getName());
        self::assertTrue($methods[0]->getParameters()[0]->allowsNull());
        self::assertNull($methods[0]->getParameters()[0]->getDefaultValue());
    }
}
