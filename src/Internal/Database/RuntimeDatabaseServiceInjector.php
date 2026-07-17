<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

use BlackOps\Database\DatabaseManager;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use Symfony\Component\DependencyInjection\Container;
use Throwable;

final readonly class RuntimeDatabaseServiceInjector
{
    public function inject(ContainerInterface $container, DatabaseManager $databases): void
    {
        if (!$container instanceof Container) {
            throw new InvalidArgumentException('Runtime container does not support database service injection.');
        }

        /** @var mixed $synthetic */
        $synthetic = new ReflectionObject($container)
            ->getProperty('syntheticIds')
            ->getValue($container);
        $serviceIds = $container->getServiceIds();
        $managerDefinition = is_array($synthetic) && ($synthetic[DatabaseManager::class] ?? false) === true;
        $connectionDefinition = is_array($synthetic) && ($synthetic[Connection::class] ?? false) === true;

        if (!$managerDefinition && !in_array(DatabaseManager::class, $serviceIds, strict: true)) {
            throw new InvalidArgumentException('Runtime container is missing valid database service definitions.');
        }

        if (!$connectionDefinition && !in_array(Connection::class, $serviceIds, strict: true)) {
            throw new InvalidArgumentException('Runtime container is missing valid database service definitions.');
        }

        try {
            $container->set(DatabaseManager::class, $databases);
            $container->set(Connection::class, $databases->connection());
        } catch (Throwable) {
            throw new InvalidArgumentException('Runtime container is missing valid database service definitions.');
        }
    }
}
