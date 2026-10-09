<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use Doctrine\DBAL\Connection;

final class PostgreSqlAuditCheckpointTransactionPolicy
{
    private function __construct() {}

    public static function root(#[\SensitiveParameter] Connection $connection): bool
    {
        try {
            $autoCommit = $connection->isAutoCommit();
            $active = $connection->isTransactionActive();
            $level = $connection->getTransactionNestingLevel();

            return $autoCommit === true && $active === false && $level === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function owned(#[\SensitiveParameter] Connection $connection): bool
    {
        try {
            return (
                $connection->isAutoCommit() === true
                && $connection->isTransactionActive() === true
                && $connection->getTransactionNestingLevel() === 1
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public static function map(#[\SensitiveParameter] \Throwable $failure): AuditStoreFailure
    {
        if ($failure instanceof AuditStoreException) {
            return $failure->failure();
        }
        if ($failure instanceof AuditProtectionException) {
            return AuditStoreFailure::ResourceUnavailable;
        }
        if ($failure instanceof AuditValidationException || $failure instanceof AuditArtifactException) {
            return AuditStoreFailure::IntegrityInvalid;
        }

        return AuditStoreFailure::StorageUnavailable;
    }
}
