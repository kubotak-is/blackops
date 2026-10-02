<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;

final class ObserverReplayAuditWriterEmission
{
    private function __construct() {}

    public static function write(
        #[\SensitiveParameter]
        \SensitiveParameterValue $snapshot,
        #[\SensitiveParameter]
        string $eventType,
        #[\SensitiveParameter]
        string $subject,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        try {
            $values = ObserverReplayAuditWriterBinding::values($snapshot);
            ObserverReplayAuditWriterPolicy::assertAuditId($auditId);
            ObserverReplayAuditWriterPolicy::assertSubject($eventType, $subject);
            $catalog = ObserverReplayAuditWriterCatalog::catalog();
            $definition = ObserverReplayAuditWriterDefinition::definition($catalog, $eventType);
            $tenantReference = ObserverReplayAuditWriterRecord::tenantReference($tenant);
            $occurredAt = ObserverReplayAuditWriterRecord::occurredAt([
                'clock' => $values['clock'],
            ]);
            $actionContext = $eventType === 'replay.observer.progressed'
                ? 'observer-replay:' . $eventType . ':' . $auditId . ':' . $subject
                : 'observer-replay:' . $eventType . ':' . $auditId;
            $resourceContext = $eventType === 'replay.observer.progressed'
                ? 'observer-replay:record:' . $subject
                : 'observer-replay:checkpoint:' . $subject;
            $actionId = ObserverReplayAuditWriterRecord::opaqueId(
                ['opaqueIds' => $values['opaqueIds']],
                AuditOpaqueIdPurpose::Action,
                $actionContext,
            );
            $resourceId = ObserverReplayAuditWriterRecord::opaqueId(
                ['opaqueIds' => $values['opaqueIds']],
                AuditOpaqueIdPurpose::Resource,
                $resourceContext,
            );
            $draft = ObserverReplayAuditWriterRecord::draft([
                'values' => $values,
                'definition' => $definition,
                'actionId' => $actionId,
                'resourceId' => $resourceId,
                'occurredAt' => $occurredAt,
                'tenant' => $tenantReference,
            ]);
            $request = new AuditDeliveryRequest($draft, 'observer-replay:' . $actionId);
            $plan = ObserverReplayAuditWriterRecord::selector([
                'selector' => $values['selector'],
            ])->forDraft($draft);
            $delivery = $values['delivery'] ?? null;
            if (!$delivery instanceof AuditDeliveryCoordinator) {
                throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
            }
            $delivery->deliverInCurrentTransaction($plan, $request);
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
