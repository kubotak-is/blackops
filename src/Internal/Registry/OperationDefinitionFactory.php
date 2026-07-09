<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationProvider;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationDefinitionFactory
{
    /**
     * @param iterable<OperationProvider> $providers
     *
     * @return list<Operation>
     */
    public function fromProviders(iterable $providers): array
    {
        $definitions = [];

        foreach ($providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $definitions[] = $this->create($definition);
            }
        }

        return $definitions;
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
