<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use Doctrine\DBAL\Connection;

interface PostgreSqlStorageProtectionRotationPhaseHook
{
    public function assertEntry(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void;

    public function assertRow(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
    ): void;

    public function started(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void;

    public function interrupted(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void;

    public function progressed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
        #[\SensitiveParameter]
        string $cursor,
    ): void;

    public function finished(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        StorageProtectionRotationAuditCompletion $completion,
    ): void;
}
