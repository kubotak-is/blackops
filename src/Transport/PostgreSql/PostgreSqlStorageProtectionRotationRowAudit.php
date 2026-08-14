<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;

final readonly class PostgreSqlStorageProtectionRotationRowAudit
{
    public function __construct(
        public string $checkpoint,
        public string $audit,
        public ?string $auditId,
        public StorageProtectionRotationCounts $totals,
    ) {}
}
