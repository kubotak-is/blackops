<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

use InvalidArgumentException;

final readonly class NamedDatabaseConfigurationNormalizer
{
    /**
     * @param array<array-key, mixed> $database
     * @return array{
     *     default: string,
     *     connections: array<string, array<string, mixed>>,
     *     framework_connection: string,
     *     schema: string
     * }
     */
    public function normalize(array $database): array
    {
        $validator = new DatabaseConfigurationValueValidator();
        $default = $validator->name($database['default'] ?? null, 'database.default');
        $connections = $this->connections($database['connections'] ?? null, $validator);

        if (!array_key_exists($default, $connections)) {
            throw new InvalidArgumentException(
                'Application configuration key "database.default" references an unknown connection.',
            );
        }

        /** @var mixed $framework */
        $framework = $database['framework'] ?? null;
        if (!is_array($framework)) {
            throw new InvalidArgumentException('Application configuration key "database.framework" must be an array.');
        }

        $frameworkConnection = $validator->name($framework['connection'] ?? null, 'database.framework.connection');
        if (!array_key_exists($frameworkConnection, $connections)) {
            throw new InvalidArgumentException(
                'Application configuration key "database.framework.connection" references an unknown connection.',
            );
        }

        return [
            'default' => $default,
            'connections' => $connections,
            'framework_connection' => $frameworkConnection,
            'schema' => $validator->schema($framework['schema'] ?? null, 'database.framework.schema'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function connections(mixed $configured, DatabaseConfigurationValueValidator $validator): array
    {
        if (!is_array($configured) || $configured === []) {
            throw new InvalidArgumentException(
                'Application configuration key "database.connections" must be a non-empty map.',
            );
        }

        $connections = [];
        /** @var mixed $parameters */
        foreach ($configured as $name => $parameters) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    'Application configuration key "database.connections" must use string names.',
                );
            }

            $normalized = $validator->name($name, 'database.connections');
            if (array_key_exists($normalized, $connections)) {
                throw new InvalidArgumentException(
                    'Application configuration key "database.connections" contains a duplicate name.',
                );
            }

            $connections[$normalized] = $validator->parameters($parameters, 'database.connections');
        }

        return $connections;
    }
}
