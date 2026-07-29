<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

final readonly class ScheduledOperationDefinitionResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(OperationMetadata $metadata): Operation
    {
        if ($metadata->definition === $metadata->handler) {
            /** @var mixed $service */
            $service = $this->container->get($metadata->handler);
            if (!$service instanceof Operation || !$service instanceof $metadata->definition) {
                throw new LogicException('Scheduled operation service does not match its definition.');
            }

            return $service;
        }

        $reflection = new ReflectionClass($metadata->definition);
        if (!$reflection->isInstantiable()) {
            throw new LogicException('Scheduled operation definition is not instantiable.');
        }
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new LogicException('Scheduled operation definition requires constructor arguments.');
        }
        $definition = $reflection->newInstance();
        if (!is_a($definition::class, Operation::class, allow_string: true)) {
            throw new LogicException('Scheduled operation definition has an invalid runtime type.');
        }

        return $definition;
    }
}
