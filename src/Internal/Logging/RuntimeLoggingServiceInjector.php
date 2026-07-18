<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Internal\Execution\ExecutionScopeProvider;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use Symfony\Component\DependencyInjection\Container;
use Throwable;

final readonly class RuntimeLoggingServiceInjector
{
    public function inject(
        ContainerInterface $container,
        ExecutionScopeProvider $scope,
        ?LoggerInterface $backend = null,
    ): ExecutionScopedLogger {
        if (!$container instanceof Container) {
            throw new InvalidArgumentException('Runtime container does not support logging service injection.');
        }

        /** @var mixed $synthetic */
        $synthetic = new ReflectionObject($container)
            ->getProperty('syntheticIds')
            ->getValue($container);
        $serviceIds = $container->getServiceIds();
        $loggerDefinition = is_array($synthetic) && ($synthetic[LoggerInterface::class] ?? false) === true;

        if (!$loggerDefinition && !in_array(LoggerInterface::class, $serviceIds, strict: true)) {
            throw new InvalidArgumentException('Runtime container is missing a valid logging service definition.');
        }

        $logger = new ExecutionScopedLogger(
            $backend ?? new MonologJsonlLoggerFactory()->create('php://stderr'),
            $scope,
        );

        try {
            $container->set(LoggerInterface::class, $logger);
        } catch (Throwable) {
            throw new InvalidArgumentException('Runtime container is missing a valid logging service definition.');
        }

        return $logger;
    }
}
