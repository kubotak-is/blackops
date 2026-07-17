<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

use BlackOps\Database\DatabaseManager;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DoctrineDatabaseManager implements DatabaseManager
{
    /** @var array<string, Connection> */
    private array $instances = [];

    /** @var Closure(array<string, mixed>): Connection */
    private readonly Closure $factory;

    /**
     * @param array<string, array<string, mixed>> $connections
     * @param null|Closure(array<string, mixed>): Connection $factory
     */
    public function __construct(
        private readonly string $default,
        private readonly array $connections,
        ?Closure $factory = null,
    ) {
        $this->factory = $factory ?? self::createConnection(...);
    }

    public function connection(?string $name = null): Connection
    {
        $resolved = $name === null ? $this->default : trim($name);

        if ($resolved === '' || !array_key_exists($resolved, $this->connections)) {
            throw new InvalidArgumentException('Unknown database connection name.');
        }

        if (array_key_exists($resolved, $this->instances)) {
            return $this->instances[$resolved];
        }

        try {
            $connection = ($this->factory)($this->connections[$resolved]);
        } catch (Throwable) {
            throw new RuntimeException('Database connection instance could not be created.');
        }

        return $this->instances[$resolved] = $connection;
    }

    /** @param array<string, mixed> $parameters */
    private static function createConnection(array $parameters): Connection
    {
        /** @var callable(array<string, mixed>): Connection $factory */
        $factory = [DriverManager::class, 'getConnection'];

        return $factory($parameters);
    }
}
