<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

final readonly class LegacyDatabaseConfigurationNormalizer
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

        return [
            'default' => 'default',
            'connections' => [
                'default' => $validator->parameters($database['connection'] ?? null, 'database.connection'),
            ],
            'framework_connection' => 'default',
            'schema' => $validator->schema($database['schema'] ?? null, 'database.schema'),
        ];
    }
}
