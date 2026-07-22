<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class SeederTest extends TestCase
{
    public function testPublicContractsExposeOnlyRun(): void
    {
        foreach ([Seeder::class, SeederRunner::class] as $contract) {
            $reflection = new ReflectionClass($contract);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            self::assertTrue($reflection->isInterface());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
            self::assertSame(
                ['run'],
                array_map(static fn(ReflectionMethod $method): string => $method->getName(), $methods),
            );
            self::assertSame('void', (string) $methods[0]->getReturnType());
        }

        $parameters = new ReflectionMethod(SeederRunner::class, 'run')->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('string', (string) $parameters[0]->getType());
        self::assertTrue($parameters[0]->isVariadic());
    }
}
