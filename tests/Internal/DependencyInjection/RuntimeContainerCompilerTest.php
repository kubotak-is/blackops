<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

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
