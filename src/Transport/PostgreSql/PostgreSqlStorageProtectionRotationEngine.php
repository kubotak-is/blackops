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
        #[\SensitiveParameter]
        private Connection $connection,
        #[\SensitiveParameter]
        BopdEnvelopeCodec $codec,
        #[\SensitiveParameter]
        private string $schema = 'blackops',
        #[\SensitiveParameter]
        private ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {
        $this->batch = new PostgreSqlStorageProtectionRotationBatch(
            $connection,
            $codec,
            new PostgreSqlStorageProtectionRotationQuery($connection, $codec, $schema),
            new PostgreSqlStorageProtectionRotationAudit($connection, $phaseHook),
            $schema,
            $phaseHook,
        );
    }

    private PostgreSqlStorageProtectionRotationBatch $batch;

    public function plan(#[\SensitiveParameter] StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        $this->assertEntry($scope);
        return $this->runInternal($scope, StorageProtectionRotationMode::Plan);
    }

    public function rotate(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): StorageProtectionRotationResult {
        if (!$scope->confirmed) {
            throw new \InvalidArgumentException('Rotation requires explicit confirmation.');
        }
        $this->assertEntry($scope);
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
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        StorageProtectionRotationMode $mode,
    ): StorageProtectionRotationResult {
        return $this->batch->run($scope, $mode);
    }

    private function assertEntry(#[\SensitiveParameter] StorageProtectionRotationScope $scope): void
    {
        if ($this->phaseHook !== null) {
            $this->phaseHook->assertEntry($this->connection, $scope);
        }
    }
}
