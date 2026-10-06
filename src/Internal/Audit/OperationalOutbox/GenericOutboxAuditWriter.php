<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final class GenericOutboxAuditWriter
{
    private ?\SensitiveParameterValue $snapshot = null;

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
        AuditSourceRuntime $runtime,
        #[\SensitiveParameter]
        string $buildId,
    ) {
        try {
            $captured = GenericOutboxAuditProjection::capture($actor, $correlation, $runtime, $buildId);
            $this->snapshot = new \SensitiveParameterValue([
                'opaqueIds' => $opaqueIds,
                'selector' => $selector,
                'delivery' => $delivery,
                'clock' => $clock,
                'actor' => $captured['actor'],
                'correlation' => $captured['correlation'],
                'runtime' => $captured['runtime'],
                'buildId' => $captured['buildId'],
            ]);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::ConfigurationInvalid);
        }
    }

    public function assertConnection(
        #[\SensitiveParameter]
        Connection $connection,
        AuditSourceRuntime $expectedRuntime,
    ): void {
        $values = GenericOutboxAuditSnapshot::read($this->snapshot);
        if (
            !in_array($expectedRuntime, [AuditSourceRuntime::Relay, AuditSourceRuntime::Cli], strict: true)
            || $values['runtime'] !== $expectedRuntime
        ) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
        try {
            if (!$values['delivery']->usesConnection($connection)) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public function retryScheduled(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $stateVersion,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('outbox.delivery.retry_scheduled', $recordId, $stateVersion, $tenant);
    }

    public function deadLettered(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $stateVersion,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('outbox.delivery.dead_lettered', $recordId, $stateVersion, $tenant);
    }

    public function deadLetterRetryRequested(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $retryAuditId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('outbox.delivery.dead_letter_retry_requested', $recordId, $retryAuditId, $tenant);
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

    private function write(
        #[\SensitiveParameter]
        string $eventType,
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $discriminator,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        try {
            $values = GenericOutboxAuditSnapshot::read($this->snapshot);
            $definition = GenericOutboxAuditCatalog::definition($eventType, $values['runtime']);
            $recordId = GenericOutboxAuditIdentity::recordId($recordId);
            $discriminator = GenericOutboxAuditIdentity::discriminator($values['runtime'], $discriminator);
            $tenantReference = GenericOutboxAuditIdentity::tenantReference($tenant);
            $occurredAt = GenericOutboxAuditProjection::occurredAt($values['clock']);
            $resourceId = GenericOutboxAuditProjection::opaqueId(
                $values['opaqueIds'],
                AuditOpaqueIdPurpose::Resource,
                'generic-outbox:record:' . $recordId,
            );
            $actionId = GenericOutboxAuditProjection::opaqueId(
                $values['opaqueIds'],
                AuditOpaqueIdPurpose::Action,
                'generic-outbox:' . $eventType . ':' . $recordId . ':' . $discriminator,
            );
            $source = [
                'runtime' => $values['runtime'],
                'buildId' => $values['buildId'],
            ];
            $material = [
                'actionId' => $actionId,
                'resourceId' => $resourceId,
                'occurredAt' => $occurredAt,
                'tenant' => $tenantReference,
            ];
            $draft = GenericOutboxAuditProjection::draft(
                $definition,
                $values['actor'],
                $values['correlation'],
                $source,
                $material,
            );
            $request = new AuditDeliveryRequest($draft, 'generic-outbox:' . $actionId);
            $plan = $values['selector']->forDraft($draft);
            $values['delivery']->deliverInCurrentTransaction($plan, $request);
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
