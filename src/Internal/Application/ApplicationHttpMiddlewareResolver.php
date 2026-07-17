<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

final readonly class ApplicationHttpMiddlewareResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /** @return list<MiddlewareInterface> */
    public function resolve(ApplicationHttpMiddlewareConfiguration $configuration): array
    {
        $resolved = [];

        foreach ($configuration->http as $id) {
            if (!$this->container->has($id)) {
                throw new LogicException('Configured HTTP middleware service is unavailable.');
            }

            try {
                /** @var mixed $middleware */
                $middleware = $this->container->get($id);
            } catch (Throwable) {
                throw new LogicException('Configured HTTP middleware service could not be resolved.');
            }

            if (!$middleware instanceof MiddlewareInterface) {
                throw new LogicException('Configured HTTP middleware service must implement MiddlewareInterface.');
            }

            $resolved[] = $middleware;
        }

        return $resolved;
    }
}
