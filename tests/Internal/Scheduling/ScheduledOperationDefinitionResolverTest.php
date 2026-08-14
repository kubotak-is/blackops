<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Scheduling\ScheduledOperationDefinitionResolver;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ScheduledOperationDefinitionResolverTest extends TestCase
{
    public function testSelfHandledDefinitionResolvesTheCompiledContainerInstance(): void
    {
        $operation = new ResolverSelfHandledOperation();
        $resolver = new ScheduledOperationDefinitionResolver(new ResolverContainer([
            ResolverSelfHandledOperation::class => $operation,
        ]));

        self::assertSame(
            $operation,
            $resolver->resolve($this->metadata(
                ResolverSelfHandledOperation::class,
                ResolverSelfHandledOperation::class,
            )),
        );
    }

    public function testSeparateConstructorlessDefinitionIsInstantiatedSafely(): void
    {
        $resolved = new ScheduledOperationDefinitionResolver(new ResolverContainer())->resolve($this->metadata(
            ResolverConstructorlessDefinition::class,
            ResolverHandler::class,
        ));

        self::assertInstanceOf(ResolverConstructorlessDefinition::class, $resolved);
    }

    public function testSeparateDefinitionWithRequiredConstructorIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires constructor arguments');

        new ScheduledOperationDefinitionResolver(new ResolverContainer())->resolve($this->metadata(
            ResolverRequiredConstructorDefinition::class,
            ResolverHandler::class,
        ));
    }

    private function metadata(string $definition, string $handler): OperationMetadata
    {
        return new OperationMetadata(
            'resolver.test',
            $definition,
            ResolverValue::class,
            $handler,
            ResolverOutcome::class,
            Inline::class,
        );
    }
}

final class ResolverContainer implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(
        private array $services = [],
    ) {}

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final readonly class ResolverSelfHandledOperation implements Operation {}

final readonly class ResolverConstructorlessDefinition implements Operation {}

final readonly class ResolverRequiredConstructorDefinition implements Operation
{
    public function __construct(string $required) {}
}

final readonly class ResolverHandler {}

final readonly class ResolverValue implements OperationValue {}

final readonly class ResolverOutcome implements Outcome {}
