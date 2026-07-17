<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

use InvalidArgumentException;

final readonly class DatabaseConfigurationNormalizer
{
    /**
     * @return array{
     *     default: string,
     *     connections: array<string, array<string, mixed>>,
     *     framework_connection: string,
     *     schema: string
     * }
     */
    public function normalize(mixed $database): array
    {
        if (!is_array($database)) {
            throw new InvalidArgumentException('Application configuration key "database" must be an array.');
        }

        $legacy = array_key_exists('connection', $database) || array_key_exists('schema', $database);
        $canonical =
            array_key_exists('default', $database)
            || array_key_exists('connections', $database)
            || array_key_exists('framework', $database);

        if ($legacy && $canonical) {
            throw new InvalidArgumentException(
                'Application database configuration cannot mix canonical and legacy keys.',
            );
        }

        return $legacy
            ? new LegacyDatabaseConfigurationNormalizer()->normalize($database)
            : new NamedDatabaseConfigurationNormalizer()->normalize($database);
    }
}
