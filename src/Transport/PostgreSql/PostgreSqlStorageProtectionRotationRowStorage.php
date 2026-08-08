<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlStorageProtectionRotationRowStorage
{
    public function __construct(
        public PostgreSqlStorageProtectionRotationTarget $target,
        public string $table,
    ) {}
}
