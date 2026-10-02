<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayPhaseHook;
use Doctrine\DBAL\Connection;

/**
 * Validates native replay phase subjects and forwards them to the audit
 * writer while the native root still owns its transaction.
 */
final class ObserverReplayAuditHook implements PostgreSqlObserverReplayPhaseHook
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] ObserverReplayAuditWriter $writer)
    {
        $this->snapshot = new \SensitiveParameterValue(['writer' => $writer]);
    }

    public function assertEntry(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
    ): void {
        ObserverReplayAuditHookGuard::entry($this->snapshot, $connection);
        try {
            ObserverReplayAuditHookGuard::checkpoint($checkpoint);
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function started(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
    ): void {
        $writer = ObserverReplayAuditHookGuard::root($this->snapshot, $connection);
        try {
            ObserverReplayAuditHookGuard::checkpoint($checkpoint);
            ObserverReplayAuditHookGuard::auditId($auditId);
            $writer->started($checkpoint, $auditId);
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function progressed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $cursor,
        #[\SensitiveParameter]
        JournalRecord $source,
    ): void {
        $writer = ObserverReplayAuditHookGuard::root($this->snapshot, $connection);
        try {
            ObserverReplayAuditHookGuard::checkpoint($checkpoint);
            ObserverReplayAuditHookGuard::auditId($auditId);
            $lineage = ObserverReplayAuditHookLineage::validate($cursor, $source);
            $writer->progressed($auditId, $lineage['recordId'], $lineage['tenant']);
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function finished(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $state,
    ): void {
        $writer = ObserverReplayAuditHookGuard::root($this->snapshot, $connection);
        try {
            ObserverReplayAuditHookGuard::checkpoint($checkpoint);
            ObserverReplayAuditHookGuard::auditId($auditId);
            if ($state !== 'paused' && $state !== 'complete') {
                throw new \LogicException();
            }
            if ($state === 'complete') {
                $writer->succeeded($checkpoint, $auditId);
            }
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function failed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
    ): void {
        $writer = ObserverReplayAuditHookGuard::root($this->snapshot, $connection);
        try {
            ObserverReplayAuditHookGuard::checkpoint($checkpoint);
            ObserverReplayAuditHookGuard::auditId($auditId);
            $writer->failed($checkpoint, $auditId);
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
    }

    public function __clone(): void
    {
        throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
    }
}
