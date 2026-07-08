<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\Execution\HandlerResolver;
use PHPUnit\Framework\TestCase;

final class ServiceProviderBoundaryTest extends TestCase
{
    public function testServiceProviderRegistersAutowiredHandler(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new ProviderFixture()]);

        $handler = new HandlerResolver($compiler->compile($builder))->resolve(ProviderHandler::class);

        self::assertInstanceOf(ProviderHandler::class, $handler);
        self::assertSame('provider-ready', $handler->dependency->value);
    }

    public function testServiceProviderRegistersObjectService(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new ObjectProviderFixture()]);

        $service = $compiler->compile($builder)->get('provider.object');

        self::assertInstanceOf(ProviderDependency::class, $service);
        self::assertSame('object-ready', $service->value);
    }
}

final readonly class ProviderFixture implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ProviderDependency::class);
        $services->autowire(ProviderHandler::class);
    }
}

final readonly class ObjectProviderFixture implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->set('provider.object', new ProviderDependency('object-ready'));
    }
}

final readonly class ProviderDependency
{
    public function __construct(
        public string $value = 'provider-ready',
    ) {}
}

final readonly class ProviderHandler implements OperationHandler
{
    public function __construct(
        public ProviderDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new EmptyOutcome());
    }
}
