<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use InvalidArgumentException;

final readonly class PostgreSqlMigrationSchema
{
    public function __construct(
        private string $name,
    ) {
        if (strlen($name) < 1 || strlen($name) > 63 || preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/', $name) !== 1) {
            throw new InvalidArgumentException('PostgreSQL schema name must be a safe identifier.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function quoted(): string
    {
        return '"' . $this->name . '"';
    }

    public function table(string $table): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('PostgreSQL table name must be a safe identifier.');
        }

        return $this->quoted() . '."' . $table . '"';
    }

    /** @return non-empty-string */
    public function doctrineTable(string $table): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('PostgreSQL table name must be a safe identifier.');
        }

        return $this->name . '.' . $table;
    }
}
