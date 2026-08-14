<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlStorageProtectionRotationBatchTables
{
    public function __construct(
        public string $table,
        public string $checkpoint,
        public string $audit,
    ) {}
}
