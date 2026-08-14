<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;

final readonly class PostgreSqlStorageProtectionRotationRowRequest
{
    /** @param array<string,mixed> $row */
    public function __construct(
        public StorageProtectionRotationScope $scope,
        public StorageProtectionRotationMode $mode,
        public PostgreSqlStorageProtectionRotationRowStorage $storage,
        public PostgreSqlStorageProtectionRotationRowAudit $audit,
        public array $row,
    ) {}
}
