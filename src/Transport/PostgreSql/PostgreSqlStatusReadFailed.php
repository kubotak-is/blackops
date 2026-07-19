<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use RuntimeException;

final class PostgreSqlStatusReadFailed extends RuntimeException
{
    private function __construct(
        public readonly PostgreSqlStatusFailureKind $kind,
    ) {
        parent::__construct('PostgreSQL status read failed.');
    }

    public static function storage(): self
    {
        return new self(PostgreSqlStatusFailureKind::Storage);
    }

    public static function integrity(): self
    {
        return new self(PostgreSqlStatusFailureKind::Integrity);
    }
}
