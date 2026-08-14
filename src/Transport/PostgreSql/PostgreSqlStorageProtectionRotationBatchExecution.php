<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;

final readonly class PostgreSqlStorageProtectionRotationBatchExecution
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(
        public PostgreSqlStorageProtectionRotationTarget $target,
        public StorageProtectionRotationMode $mode,
        public ?string $auditId,
        public array $rows,
        public StorageProtectionRotationCounts $counts,
    ) {}
}
