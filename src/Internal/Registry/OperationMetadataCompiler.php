<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationMetadataCompiler
{
    public function __construct(
        private OperationHandlerMetadataCompiler $handlers = new OperationHandlerMetadataCompiler(),
    ) {}

    /** @param class-string<Operation> $definition */
    public function compile(string $definition): OperationMetadata
    {
        $reflection = new ReflectionClass($definition);
        if (!$reflection->implementsInterface(Operation::class)) {
            throw new InvalidArgumentException('Operation definition must implement Operation.');
        }
        $typeAttributes = $reflection->getAttributes(OperationType::class);
        $acceptsAttributes = $reflection->getAttributes(Accepts::class);
        $handlerAttributes = $reflection->getAttributes(HandledBy::class);
        $returnsAttributes = $reflection->getAttributes(Returns::class);
        if (count($typeAttributes) !== 1 || count($acceptsAttributes) !== 1 || count($returnsAttributes) !== 1) {
            throw new InvalidArgumentException('Operation definition requires each metadata attribute exactly once.');
        }
        if (count($handlerAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat HandledBy.');
        }
        $type = $typeAttributes[0]->newInstance();
        $accepts = $acceptsAttributes[0]->newInstance();
        $returns = $returnsAttributes[0]->newInstance();
        [$handler, $typedSelfHandled, $typedSelfHandledContext] = $this->handlers->compile(
            $reflection,
            $handlerAttributes,
            $accepts->value,
        );
        $strategyAttributes = $reflection->getAttributes(ExecuteWith::class);
        if (count($strategyAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat ExecuteWith.');
        }
        $strategy = $strategyAttributes === [] ? Inline::class : $strategyAttributes[0]->newInstance()->strategy;
        $this->assertImplements($accepts->value, OperationValue::class);
        $this->assertImplements($returns->outcome, Outcome::class);
        $this->assertImplements($strategy, ExecutionStrategy::class);
        return new OperationMetadata(
            $type->id,
            $definition,
            $accepts->value,
            $handler,
            $returns->outcome,
            $strategy,
            $typedSelfHandled,
            $typedSelfHandledContext,
        );
    }

    /** @param class-string $class @param class-string $interface */
    private function assertImplements(string $class, string $interface): void
    {
        if (!is_a($class, $interface, allow_string: true)) {
            throw new InvalidArgumentException('Operation metadata class does not implement its required contract.');
        }
    }
}
