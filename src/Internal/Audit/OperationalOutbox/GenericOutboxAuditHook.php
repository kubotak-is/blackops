<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxPhaseHook;
use Doctrine\DBAL\Connection;

final class GenericOutboxAuditHook implements PostgreSqlOutboxPhaseHook
{
    private ?\SensitiveParameterValue $snapshot = null;

    public function __construct(#[\SensitiveParameter] GenericOutboxAuditWriter $writer)
    {
        $this->snapshot = new \SensitiveParameterValue(['writer' => $writer]);
    }

    public function assertRelayEntry(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            $writer = GenericOutboxAuditHookGuard::writer($this->snapshot);
            $writer->assertConnection($connection, AuditSourceRuntime::Relay);
            GenericOutboxAuditHookGuard::entry($connection);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public function assertOperatorEntry(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            $writer = GenericOutboxAuditHookGuard::writer($this->snapshot);
            $writer->assertConnection($connection, AuditSourceRuntime::Cli);
            GenericOutboxAuditHookGuard::entry($connection);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    /** @param array<string,mixed> $row */
    public function retryScheduled(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        array $row,
    ): void {
        try {
            $writer = GenericOutboxAuditHookGuard::writer($this->snapshot);
            $writer->assertConnection($connection, AuditSourceRuntime::Relay);
            GenericOutboxAuditHookGuard::root($connection);
            $value = GenericOutboxAuditRow::read($row);
            $writer->retryScheduled($value['record_id'], $value['state_version'], $value['tenant']);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /** @param array<string,mixed> $row */
    public function deadLettered(#[\SensitiveParameter] Connection $connection, #[\SensitiveParameter] array $row): void
    {
        try {
            $writer = GenericOutboxAuditHookGuard::writer($this->snapshot);
            $writer->assertConnection($connection, AuditSourceRuntime::Relay);
            GenericOutboxAuditHookGuard::root($connection);
            $value = GenericOutboxAuditRow::read($row);
            $writer->deadLettered($value['record_id'], $value['state_version'], $value['tenant']);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /** @param array<string,mixed> $row */
    public function deadLetterRetryRequested(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $retryAuditId,
    ): void {
        try {
            $writer = GenericOutboxAuditHookGuard::writer($this->snapshot);
            $writer->assertConnection($connection, AuditSourceRuntime::Cli);
            GenericOutboxAuditHookGuard::root($connection);
            $value = GenericOutboxAuditRow::read($row);
            $writer->deadLetterRetryRequested($value['record_id'], $retryAuditId, $value['tenant']);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
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
        throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
    }

    public function __clone(): void
    {
        throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
    }
}
