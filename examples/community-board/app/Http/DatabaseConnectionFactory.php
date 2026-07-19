<?php

declare(strict_types=1);

namespace App\Http;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;

final readonly class DatabaseConnectionFactory
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        private array $parameters,
    ) {}

    /** @param array<string, string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $port = filter_var($environment['POSTGRES_PORT'] ?? '5432', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65_535],
        ]);
        if (!is_int($port)) {
            throw new InvalidArgumentException('Database configuration is invalid.');
        }

        return new self([
            'driver' => 'pdo_pgsql',
            'host' => $environment['POSTGRES_HOST'] ?? '127.0.0.1',
            'port' => $port,
            'dbname' => $environment['POSTGRES_DB'] ?? 'community_board',
            'user' => $environment['POSTGRES_USER'] ?? 'blackops',
            'password' => $environment['POSTGRES_PASSWORD'] ?? 'blackops',
        ]);
    }

    public function create(): Connection
    {
        return DriverManager::getConnection($this->parameters);
    }
}
