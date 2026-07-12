<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use LogicException;
use Psr\Container\ContainerInterface;

final readonly class HandlerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /** @param class-string $handler */
    public function resolve(string $handler): object
    {
        return $this->requireService($this->container->get($handler), $handler);
    }

    /** @param class-string $handler */
    private function requireService(mixed $service, string $handler): object
    {
        if (!is_object($service) || !$service instanceof $handler) {
            throw new LogicException('Resolved handler service does not match operation metadata.');
        }

        return $service;
    }
}
