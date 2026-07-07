<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\OperationHandler;
use LogicException;
use Psr\Container\ContainerInterface;

final readonly class HandlerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /** @param class-string<OperationHandler> $handler */
    public function resolve(string $handler): OperationHandler
    {
        return $this->requireHandler($this->container->get($handler));
    }

    private function requireHandler(mixed $service): OperationHandler
    {
        if (!$service instanceof OperationHandler) {
            throw new LogicException('Resolved handler service does not implement OperationHandler.');
        }

        return $service;
    }
}
