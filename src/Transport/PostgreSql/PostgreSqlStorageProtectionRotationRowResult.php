<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlStorageProtectionRotationRowResult
{
    public function __construct(
        public int $selected,
        public int $rotated,
        public int $skipped,
        public int $failed,
        public bool $failedRow,
    ) {}
}
