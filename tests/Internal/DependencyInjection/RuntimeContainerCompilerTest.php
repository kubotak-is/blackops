<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Console\DumpHttpManifestCommand;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\Execution\HandlerResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

final class RuntimeContainerCompilerTest extends TestCase
{
    public function testCompiledContainerCanResolveAutowiredHandler(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true)->setPublic(true);
        $builder->register(ContainerHandler::class)->setAutowired(true)->setPublic(true);

        $container = $compiler->compile($builder);
        $handler = new HandlerResolver($container)->resolve(ContainerHandler::class);

        self::assertInstanceOf(ContainerHandler::class, $handler);
        self::assertSame('dependency-ready', $handler->dependency->value);
    }

    public function testCompiledContainerCanResolveHttpManifestCommand(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $definition = new ContainerOperation();
        $builder->set('operation.registry', new OperationRegistry([$this->metadata()]));
        $builder->set('operation.definitions', new ContainerDefinitions($definition));
        $builder->register(HttpOperationManifestFile::class)->setPublic(true);
        $builder
            ->register(DumpHttpManifestCommand::class)
            ->setArguments([
                new Reference('operation.registry'),
                new Reference('operation.definitions'),
                new Reference(HttpOperationManifestFile::class),
            ])
            ->setPublic(true);

        $command = $compiler->compile($builder)->get(DumpHttpManifestCommand::class);

        self::assertInstanceOf(DumpHttpManifestCommand::class, $command);
    }

    public function testRegistersRegistryHandlersOnceAndAutowiresDependencies(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true);
        $registry = new OperationRegistry([$this->metadata()]);
        $compiler->registerHandlers($builder, $registry);
        $compiler->registerHandlers($builder, $registry);

        $handler = $compiler->compile($builder)->get(ContainerHandler::class);

        self::assertInstanceOf(ContainerHandler::class, $handler);
        self::assertSame('dependency-ready', $handler->dependency->value);
    }

    public function testExplicitProviderInstanceBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new ContainerHandler(new ContainerDependency('explicit'));
        $compiler->apply($builder, [new ExplicitHandlerProvider($expected)]);
        $compiler->registerHandlers($builder, new OperationRegistry([$this->metadata()]));

        $handler = $compiler->compile($builder)->get(ContainerHandler::class);

        self::assertSame($expected, $handler);
        self::assertSame('explicit', $handler->dependency->value);
    }

    public function testApplicationProviderInterfaceBindingIsInjectedIntoSelfHandledOperation(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $registry = new OperationRegistry([new OperationMetadata(
            'container.self.handled',
            RepositoryBackedOperation::class,
            ContainerValue::class,
            RepositoryBackedOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
        )]);
        $compiler->apply($builder, [new RepositoryBindingProvider()]);
        $compiler->registerHandlers($builder, $registry);

        $handler = $compiler->compile($builder)->get(RepositoryBackedOperation::class);

        self::assertInstanceOf(RepositoryBackedOperation::class, $handler);
        self::assertSame('repository-ready', $handler->repository->value());
    }

    public function testExplicitTypedSelfHandledBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new RepositoryBackedOperation(new ContainerRepositoryImplementation());
        $metadata = new OperationMetadata(
            'container.self.handled.explicit',
            RepositoryBackedOperation::class,
            ContainerValue::class,
            RepositoryBackedOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
        );
        $compiler->apply($builder, [new ExplicitTypedHandlerProvider($expected)]);
        $compiler->registerHandlers($builder, new OperationRegistry([$metadata]));

        self::assertSame($expected, $compiler->compile($builder)->get(RepositoryBackedOperation::class));
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'container.operation',
            ContainerOperation::class,
            ContainerValue::class,
            ContainerHandler::class,
            EmptyOutcome::class,
            Inline::class,
        );
    }
}

final readonly class ContainerDependency
{
    public function __construct(
        public string $value = 'dependency-ready',
    ) {}
}

final readonly class ContainerOperation implements Operation {}

/**
 * @implements \IteratorAggregate<int, Operation>
 */
final readonly class ContainerDefinitions implements \IteratorAggregate
{
    public function __construct(
        private Operation $definition,
    ) {}

    public function getIterator(): \Traversable
    {
        yield $this->definition;
    }
}

final readonly class ContainerValue implements OperationValue {}

final readonly class ContainerHandler implements OperationHandler
{
    public function __construct(
        public ContainerDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class ExplicitHandlerProvider implements ServiceProvider
{
    public function __construct(
        private ContainerHandler $handler,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(ContainerHandler::class, $this->handler);
    }
}

interface ContainerRepository
{
    public function value(): string;
}

final readonly class ContainerRepositoryImplementation implements ContainerRepository
{
    public function value(): string
    {
        return 'repository-ready';
    }
}

final readonly class RepositoryBindingProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ContainerRepository::class, ContainerRepositoryImplementation::class);
    }
}

final readonly class ExplicitTypedHandlerProvider implements ServiceProvider
{
    public function __construct(
        private RepositoryBackedOperation $handler,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(RepositoryBackedOperation::class, $this->handler);
    }
}

final readonly class RepositoryBackedOperation implements Operation
{
    public function __construct(
        public ContainerRepository $repository,
    ) {}

    public function handle(ContainerValue $value): OperationResult
    {
        return OperationResult::completed();
    }
}
