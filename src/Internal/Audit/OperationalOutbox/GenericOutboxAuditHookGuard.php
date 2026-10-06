<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Doctrine\DBAL\Connection;

final class GenericOutboxAuditHookGuard
{
    private function __construct() {}

    public static function writer(#[\SensitiveParameter] ?\SensitiveParameterValue $snapshot): GenericOutboxAuditWriter
    {
        try {
            if (!$snapshot instanceof \SensitiveParameterValue) {
                throw new \LogicException();
            }
            $value = $snapshot->getValue();
            if (
                !is_array($value)
                || array_keys($value) !== ['writer']
                || !$value['writer'] instanceof GenericOutboxAuditWriter
            ) {
                throw new \LogicException();
            }

            return $value['writer'];
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public static function entry(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \LogicException();
            }
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public static function root(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || !$connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 1
            ) {
                throw new \LogicException();
            }
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }
}
