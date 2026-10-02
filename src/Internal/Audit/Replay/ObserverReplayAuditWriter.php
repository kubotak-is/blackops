<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Projects native observer replay checkpoints into durable audit events.
 *
 * The writer retains only the trusted administrative binding. Selected record
 * identity is supplied by the native phase hook after source validation.
 */
final class ObserverReplayAuditWriter
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        AuditDeliverySelector $selector,
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        ClockInterface $clock,
        #[\SensitiveParameter]
        ActorContext $actor,
        #[\SensitiveParameter]
        AuditRecordCorrelation $correlation,
        #[\SensitiveParameter]
        string $buildId,
    ) {
        $this->snapshot = ObserverReplayAuditWriterBinding::capture([
            'opaqueIds' => $opaqueIds,
            'selector' => $selector,
            'delivery' => $delivery,
            'clock' => $clock,
            'actor' => $actor,
            'correlation' => $correlation,
            'buildId' => $buildId,
        ]);
    }

    public function assertConnection(#[\SensitiveParameter] Connection $connection): void
    {
        $delivery = ObserverReplayAuditWriterBinding::values($this->snapshot)['delivery'];
        if (!$delivery instanceof AuditDeliveryCoordinator) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            if (!$delivery->usesConnection($connection)) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public function started(#[\SensitiveParameter] string $checkpoint, #[\SensitiveParameter] string $auditId): void
    {
        ObserverReplayAuditWriterEmission::write(
            $this->snapshot,
            'replay.observer.started',
            $checkpoint,
            $auditId,
            null,
        );
    }

    public function succeeded(#[\SensitiveParameter] string $checkpoint, #[\SensitiveParameter] string $auditId): void
    {
        ObserverReplayAuditWriterEmission::write(
            $this->snapshot,
            'replay.observer.succeeded',
            $checkpoint,
            $auditId,
            null,
        );
    }

    public function failed(#[\SensitiveParameter] string $checkpoint, #[\SensitiveParameter] string $auditId): void
    {
        ObserverReplayAuditWriterEmission::write(
            $this->snapshot,
            'replay.observer.failed',
            $checkpoint,
            $auditId,
            null,
        );
    }

    public function progressed(
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        ObserverReplayAuditWriterEmission::write(
            $this->snapshot,
            'replay.observer.progressed',
            $recordId,
            $auditId,
            $tenant,
        );
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
