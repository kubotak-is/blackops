<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Database\DatabaseConfigurationNormalizer;
use BlackOps\Internal\Database\DoctrineDatabaseManager;

final readonly class ApplicationDatabaseConfiguration
{
    /** @var array<string, mixed> */
    public array $connection;

    /** @param array<string, array<string, mixed>> $connections */
    private function __construct(
        public string $default,
        public array $connections,
        public string $frameworkConnection,
        public string $schema,
    ) {
        $this->connection = $connections[$default];
    }

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        $database = new DatabaseConfigurationNormalizer()->normalize($configuration['database'] ?? null);

        return new self(
            $database['default'],
            $database['connections'],
            $database['framework_connection'],
            $database['schema'],
        );
    }

    public function databaseManager(): DoctrineDatabaseManager
    {
        return new DoctrineDatabaseManager($this->default, $this->connections);
    }
}
