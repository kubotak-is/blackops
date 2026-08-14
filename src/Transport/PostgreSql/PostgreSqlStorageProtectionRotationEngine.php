<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlStorageProtectionRotationEngine
{
    public function __construct(
        private Connection $connection,
        BopdEnvelopeCodec $codec,
        private string $schema = 'blackops',
    ) {
        $this->batch = new PostgreSqlStorageProtectionRotationBatch(
            $connection,
            $codec,
            new PostgreSqlStorageProtectionRotationQuery($connection, $codec, $schema),
            new PostgreSqlStorageProtectionRotationAudit($connection),
            $schema,
        );
    }

    private PostgreSqlStorageProtectionRotationBatch $batch;

    public function plan(StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        return $this->runInternal($scope, StorageProtectionRotationMode::Plan);
    }

    public function rotate(StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        if (!$scope->confirmed) {
            throw new \InvalidArgumentException('Rotation requires explicit confirmation.');
        }
        $lockKey = $this->schema . ':' . $scope->checkpoint;
        $acquired =
            $this->connection->fetchOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0))', [
                $lockKey,
            ]) === true;
        if (!$acquired) {
            throw new \RuntimeException('Rotation checkpoint is already owned.');
        }
        try {
            return $this->runInternal($scope, StorageProtectionRotationMode::Confirmed);
        } finally {
            $this->connection->executeStatement('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lockKey]);
        }
    }

    private function runInternal(
        StorageProtectionRotationScope $scope,
        StorageProtectionRotationMode $mode,
    ): StorageProtectionRotationResult {
        return $this->batch->run($scope, $mode);
    }
}
