<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use InvalidArgumentException;

final readonly class PostgreSqlIdentifier
{
    private function __construct(
        private string $value,
    ) {}

    public static function schema(string $value): self
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException('PostgreSQL schema name must be a safe identifier.');
        }

        return new self($value);
    }

    public function qualify(string $table): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('PostgreSQL table name must be a safe identifier.');
        }

        return "{$this->quote($this->value)}.{$this->quote($table)}";
    }

    public function quoted(): string
    {
        return $this->quote($this->value);
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace(search: '"', replace: '""', subject: $identifier) . '"';
    }
}
