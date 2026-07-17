<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Database;

use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class RuntimeDatabaseServiceInjectorTest extends TestCase
{
    public function testInjectsManagerAndItsDefaultConnectionIntoCompiledContainer(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerDatabaseServices($builder);
        $container = $compiler->compile($builder);
        $connection = $this->createStub(Connection::class);
        $databases = $this->createMock(DatabaseManager::class);
        $databases->expects(self::once())->method('connection')->with()->willReturn($connection);

        new RuntimeDatabaseServiceInjector()->inject($container, $databases);

        self::assertSame($databases, $container->get(DatabaseManager::class));
        self::assertSame($connection, $container->get(Connection::class));
    }

    public function testRejectsContainerWithoutRuntimeMutationSupport(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new \LogicException();
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support database service injection');

        new RuntimeDatabaseServiceInjector()->inject($container, $this->createStub(DatabaseManager::class));
    }

    public function testRejectsCompiledContainerWithoutSyntheticDefinitionsBeforeResolvingDefault(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $container = $compiler->compile($compiler->builder());
        $databases = $this->createMock(DatabaseManager::class);
        $databases->expects(self::never())->method('connection');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing valid database service definitions');

        new RuntimeDatabaseServiceInjector()->inject($container, $databases);
    }
}
