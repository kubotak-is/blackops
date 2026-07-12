<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationDatabaseConfiguration
{
    /** @param array<string, mixed> $connection */
    private function __construct(
        public array $connection,
        public string $schema,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        $database = $configuration['database'] ?? null;

        if (!is_array($database)) {
            throw new InvalidArgumentException('Application configuration key "database" must be an array.');
        }

        /** @var mixed $connection */
        $connection = $database['connection'] ?? null;
        if (!is_array($connection)) {
            throw new InvalidArgumentException('Application configuration key "database.connection" must be an array.');
        }

        foreach (array_keys($connection) as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    'Application configuration key "database.connection" must use string parameter names.',
                );
            }
        }

        /** @var mixed $schema */
        $schema = $database['schema'] ?? null;
        if (!is_string($schema) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema) !== 1) {
            throw new InvalidArgumentException(
                'Application configuration key "database.schema" must be a valid PostgreSQL identifier.',
            );
        }

        /** @var array<string, mixed> $connection */
        return new self($connection, $schema);
    }
}
