<?php

declare(strict_types=1);

namespace BlackOps\Tests\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Application\ApplicationBuilder;
use BlackOps\Application\ConsoleKernel;
use BlackOps\Application\Environment;
use BlackOps\Core\Attribute\PublicApi;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class ApplicationTest extends TestCase
{
    public function testPublicBootstrapTypesAreMarkedAndExposeRequiredFluentShape(): void
    {
        foreach ([
            Application::class,
            ApplicationBuilder::class,
            ApplicationBootstrapException::class,
            ConsoleKernel::class,
            Environment::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }

        $application = new ReflectionClass(Application::class);
        $builder = new ReflectionClass(ApplicationBuilder::class);

        self::assertTrue($application->getConstructor()?->isPrivate());
        self::assertTrue($builder->getConstructor()?->isPrivate());
        self::assertSame(ApplicationBuilder::class, (string) $application->getMethod('configure')->getReturnType());

        foreach ([
            'withEnvironment',
            'withEnvironmentFile',
            'withConfiguration',
            'withOperations',
            'withServices',
            'withCommands',
        ] as $method) {
            self::assertSame(ApplicationBuilder::class, (string) $builder->getMethod($method)->getReturnType());
        }

        self::assertSame(Application::class, (string) $builder->getMethod('create')->getReturnType());
        self::assertSame(RequestHandlerInterface::class, (string) $application->getMethod('http')->getReturnType());
        self::assertSame(ConsoleKernel::class, (string) $application->getMethod('console')->getReturnType());
        self::assertFalse($application->hasMethod('container'));

        $applicationMethods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $application->getMethods(ReflectionMethod::IS_PUBLIC),
        );
        sort($applicationMethods);
        self::assertSame(['configure', 'console', 'http'], $applicationMethods);

        $publicMethods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $builder->getMethods(ReflectionMethod::IS_PUBLIC),
        );
        sort($publicMethods);
        self::assertSame(
            [
                'create',
                'withCommands',
                'withConfiguration',
                'withEnvironment',
                'withEnvironmentFile',
                'withOperations',
                'withServices',
            ],
            $publicMethods,
        );
    }

    public function testConsoleKernelHasOnlyRunAsPublicMethod(): void
    {
        $kernel = new ReflectionClass(ConsoleKernel::class);
        self::assertTrue($kernel->isFinal());
        self::assertTrue($kernel->getConstructor()?->isPrivate());
        $methods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $kernel->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertSame(['run'], $methods);
    }

    public function testBuilderCannotBeConstructedWithAnInjectedFactory(): void
    {
        $this->expectException(ReflectionException::class);

        new ReflectionClass(ApplicationBuilder::class)->newInstanceArgs([__DIR__, static fn(): null => null]);
    }

    public function testConfigureReturnsBuilderForExistingDirectory(): void
    {
        self::assertInstanceOf(ApplicationBuilder::class, Application::configure(__DIR__));
    }
}
