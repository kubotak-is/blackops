<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Doctrine\DBAL\Connection;

final class ObserverReplayAuditHookGuard
{
    private function __construct() {}

    public static function writer(#[\SensitiveParameter] \SensitiveParameterValue $snapshot): ObserverReplayAuditWriter
    {
        try {
            $value = $snapshot->getValue();
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['writer']
            || !$value['writer'] instanceof ObserverReplayAuditWriter
        ) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value['writer'];
    }

    public static function entry(
        #[\SensitiveParameter]
        \SensitiveParameterValue $snapshot,
        #[\SensitiveParameter]
        Connection $connection,
    ): ObserverReplayAuditWriter {
        $writer = self::writer($snapshot);
        try {
            $writer->assertConnection($connection);
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }

        return $writer;
    }

    public static function root(
        #[\SensitiveParameter]
        \SensitiveParameterValue $snapshot,
        #[\SensitiveParameter]
        Connection $connection,
    ): ObserverReplayAuditWriter {
        $writer = self::writer($snapshot);
        try {
            $writer->assertConnection($connection);
            if (
                !$connection->isAutoCommit()
                || !$connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 1
            ) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }

        return $writer;
    }

    public static function checkpoint(#[\SensitiveParameter] string $checkpoint): void
    {
        ObserverReplayAuditWriterPolicy::assertSubject('replay.observer.started', $checkpoint);
    }

    public static function auditId(#[\SensitiveParameter] string $auditId): void
    {
        ObserverReplayAuditWriterPolicy::assertAuditId($auditId);
    }
}
