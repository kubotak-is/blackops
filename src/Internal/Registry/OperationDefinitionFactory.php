<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
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
        return array_map($this->create(...), $this->definitions->collect($providers, $discovered));
    }

    /**
     * @return list<Operation>
     */
    public function fromRegistry(OperationRegistry $registry): array
    {
        return array_map(fn($metadata): Operation => $this->create($metadata->definition), $registry->all());
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
