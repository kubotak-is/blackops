<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use RuntimeException;

/** Normalizes DBAL PostgreSQL bytea values without string-casting resources. */
final class PostgreSqlBytea
{
    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_resource($value)) {
            $contents = stream_get_contents($value);
            if ($contents !== false) {
                return $contents;
            }
        }
        throw new RuntimeException('PostgreSQL bytea value is unreadable.');
    }
}
