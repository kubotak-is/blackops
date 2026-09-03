<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;

final readonly class ApplicationAuditRuntime
{
    private function __construct(
        #[\SensitiveParameter]
        private AuditOutboxConfiguration $outboxConfiguration,
        #[\SensitiveParameter]
        private HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        private AuditDeliverySelector $deliverySelector,
        #[\SensitiveParameter]
        private AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        private AuditMutationTransactionCoordinator $mutations,
        #[\SensitiveParameter]
        private AuditProtectedReadGate $protectedReads,
        #[\SensitiveParameter]
        private PostgreSqlAuditRecordStore $records,
        #[\SensitiveParameter]
        private PostgreSqlAuditOutboxStore $outbox,
    ) {}

    /** @mago-expect lint:excessive-parameter-list */
    public static function fromComposition(
        #[\SensitiveParameter]
        AuditOutboxConfiguration $outboxConfiguration,
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        AuditDeliverySelector $deliverySelector,
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        AuditMutationTransactionCoordinator $mutations,
        #[\SensitiveParameter]
        AuditProtectedReadGate $protectedReads,
        #[\SensitiveParameter]
        PostgreSqlAuditRecordStore $records,
        #[\SensitiveParameter]
        PostgreSqlAuditOutboxStore $outbox,
    ): self {
        return new self(
            $outboxConfiguration,
            $opaqueIds,
            $deliverySelector,
            $delivery,
            $mutations,
            $protectedReads,
            $records,
            $outbox,
        );
    }

    public function outboxConfiguration(): AuditOutboxConfiguration
    {
        return $this->outboxConfiguration;
    }

    public function opaqueIds(): HmacAuditOpaqueIdDeriver
    {
        return $this->opaqueIds;
    }

    public function deliverySelector(): AuditDeliverySelector
    {
        return $this->deliverySelector;
    }

    public function delivery(): AuditDeliveryCoordinator
    {
        return $this->delivery;
    }

    public function mutations(): AuditMutationTransactionCoordinator
    {
        return $this->mutations;
    }

    public function protectedReads(): AuditProtectedReadGate
    {
        return $this->protectedReads;
    }

    public function records(): PostgreSqlAuditRecordStore
    {
        return $this->records;
    }

    public function outbox(): PostgreSqlAuditOutboxStore
    {
        return $this->outbox;
    }
}
