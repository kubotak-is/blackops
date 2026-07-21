<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\Registry\OperationRegistry;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationDefinitionFactory
{
    public function __construct(
        private OperationDefinitionCollector $definitions = new OperationDefinitionCollector(),
    ) {}

    /**
     * @param iterable<OperationProvider> $providers
     * @param iterable<class-string<Operation>> $discovered
     *
     * @return list<Operation>
     */
    public function fromProviders(iterable $providers, iterable $discovered = []): array
    {
        return array_map($this->create(...), $this->classNamesFromProviders($providers, $discovered));
    }

    /**
     * @param iterable<OperationProvider> $providers
     * @param iterable<class-string<Operation>> $discovered
     *
     * @return list<class-string<Operation>>
     */
    public function classNamesFromProviders(iterable $providers, iterable $discovered = []): array
    {
        return $this->definitions->collect($providers, $discovered);
    }

    /** @return list<class-string<Operation>> */
    public function classNamesFromRegistry(OperationRegistry $registry): array
    {
        return array_map(static fn($metadata): string => $metadata->definition, $registry->all());
    }

    /**
     * @param (callable(class-string): object)|null $handlerResolver
     * @return list<Operation>
     */
    public function fromRegistry(OperationRegistry $registry, ?callable $handlerResolver = null): array
    {
        return array_map(fn(OperationMetadata $metadata): Operation => $this->fromMetadata(
            $metadata,
            $handlerResolver,
        ), $registry->all());
    }

    /** @param (callable(class-string): object)|null $handlerResolver */
    public function fromMetadata(OperationMetadata $metadata, ?callable $handlerResolver = null): Operation
    {
        if (strcmp($metadata->definition, $metadata->handler) !== 0) {
            return $this->create($metadata->definition);
        }
        if ($handlerResolver === null) {
            throw new InvalidArgumentException('Self-handled operation requires a runtime handler resolver.');
        }
        $definition = $handlerResolver($metadata->handler);
        if (!$definition instanceof Operation) {
            throw new InvalidArgumentException('Self-handled service must implement Operation.');
        }

        return $definition;
    }

    /**
     * @param class-string<Operation> $definition
     */
    private function create(string $definition): Operation
    {
        if (!is_a($definition, Operation::class, allow_string: true)) {
            throw new InvalidArgumentException('Operation definition must implement Operation.');
        }

        $reflection = new ReflectionClass($definition);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException('Operation definition must be instantiable.');
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException('Operation definition must be instantiable without arguments.');
        }

        return $reflection->newInstance();
    }
}
