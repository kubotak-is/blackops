<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use RuntimeException;

final class PostgreSqlDiagnosticsReadFailed extends RuntimeException
{
    private function __construct(
        public readonly PostgreSqlDiagnosticsFailureKind $kind,
    ) {
        parent::__construct('PostgreSQL diagnostics read failed.');
    }

    public static function storage(): self
    {
        return new self(PostgreSqlDiagnosticsFailureKind::Storage);
    }

    public static function integrity(): self
    {
        return new self(PostgreSqlDiagnosticsFailureKind::Integrity);
    }
}
