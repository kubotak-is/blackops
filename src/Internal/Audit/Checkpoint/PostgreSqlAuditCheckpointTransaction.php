<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use Doctrine\DBAL\Connection;

final class PostgreSqlAuditCheckpointTransaction
{
    private string $phase = 'ready';

    private bool $closeAttempted = false;

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function beginRoot(): void
    {
        if ($this->phase !== 'ready') {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        if (!PostgreSqlAuditCheckpointTransactionPolicy::root($this->connection)) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }

        $this->phase = 'beginning';
        try {
            $this->connection->beginTransaction();
        } catch (\Throwable) {
            $this->closeOnce();
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        $this->phase = 'isolating';
        try {
            $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        } catch (\Throwable) {
            $this->rollbackOwned();
            $this->closeOnce();
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        $this->phase = 'owned';
    }

    public function commit(): void
    {
        if ($this->phase !== 'owned') {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        $this->phase = 'committing';
        try {
            $this->connection->commit();
            $this->phase = 'ready';
        } catch (\Throwable) {
            $this->rollbackOwned();
            $this->closeOnce();
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
    }

    public function abort(#[\SensitiveParameter] \Throwable $failure): AuditStoreFailure
    {
        if ($this->phase === 'ready') {
            return PostgreSqlAuditCheckpointTransactionPolicy::map($failure);
        }
        if (in_array($this->phase, ['beginning', 'quarantined', 'quarantine_close_failed'], strict: true)) {
            $this->closeOnce();
            return AuditStoreFailure::StorageUnavailable;
        }
        if (in_array($this->phase, ['isolating', 'committing'], strict: true)) {
            $this->rollbackOwned();
            $this->closeOnce();
            return AuditStoreFailure::StorageUnavailable;
        }

        $mapped = PostgreSqlAuditCheckpointTransactionPolicy::map($failure);
        if ($this->rollbackOwned() && $mapped !== AuditStoreFailure::StorageUnavailable) {
            $this->phase = 'ready';
            return $mapped;
        }
        $this->closeOnce();
        return AuditStoreFailure::StorageUnavailable;
    }

    private function rollbackOwned(): bool
    {
        try {
            if (!PostgreSqlAuditCheckpointTransactionPolicy::owned($this->connection)) {
                return false;
            }
            $this->connection->rollBack();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function closeOnce(): void
    {
        if ($this->closeAttempted) {
            return;
        }
        $this->closeAttempted = true;
        $this->phase = 'quarantined';
        try {
            $this->connection->close();
        } catch (\Throwable) {
            $this->phase = 'quarantine_close_failed';
        }
    }
}
