<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Rotation;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Projects native storage rotation phase transitions into durable audit events.
 *
 * The binding is reduced at construction and delivery remains owned by the
 * transaction supplied by the native rotation root.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class StorageRotationAuditWriter
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string CATALOG_ID = 'blackops.framework.operations';
    private const string COMPONENT = 'blackops.rotation.storage';
    private const string POLICY_ID = 'blackops.rotation.storage';
    private const string RESOURCE_TYPE = 'protected_storage';

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
        AuditSourceRuntime $runtime,
        #[\SensitiveParameter]
        string $buildId,
    ) {
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
            $principal = $actor->authorization();
            $execution = $actor->execution();
            if ($principal !== null && !$principal instanceof ActorRef || !$execution instanceof ActorRef) {
                throw new \LogicException();
            }
            $actorSnapshot = new AuditRecordActor(
                $this->reference($principal),
                $this->reference($execution) ?? throw new \LogicException(),
            );
            $correlationSnapshot = AuditRecordCorrelation::fromArray($correlation->toArray());
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->snapshot = new \SensitiveParameterValue([
            'opaqueIds' => $opaqueIds,
            'selector' => $selector,
            'delivery' => $delivery,
            'clock' => $clock,
            'actor' => $actorSnapshot,
            'correlation' => $correlationSnapshot,
            'runtime' => $runtime,
            'buildId' => $buildId,
        ]);
    }

    public function assertConnection(#[\SensitiveParameter] Connection $connection): void
    {
        $delivery = $this->value('delivery');
        if (!$delivery instanceof AuditDeliveryCoordinator) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            if (!$delivery->usesConnection($connection)) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public function started(
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $scopeHash,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('rotation.storage.started', $auditId, $scopeHash, null, $tenant);
    }

    public function failed(
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $scopeHash,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('rotation.storage.failed', $auditId, $scopeHash, null, $tenant);
    }

    public function succeeded(
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $scopeHash,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('rotation.storage.succeeded', $auditId, $scopeHash, null, $tenant);
    }

    public function progressed(
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $scopeHash,
        #[\SensitiveParameter]
        string $cursor,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write('rotation.storage.progressed', $auditId, $scopeHash, $cursor, $tenant);
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
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    public function __clone(): void
    {
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    private function write(
        string $eventType,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $scopeHash,
        #[\SensitiveParameter]
        ?string $cursor,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        try {
            $this->value('buildId');
            $this->assertAuditId($auditId);
            $this->assertScopeHash($scopeHash);
            $this->assertEventCursor($eventType, $cursor);
            $catalog = $this->catalog();
            $definition = $this->definition($catalog, $eventType);
            $tenantReference = $this->tenantReference($tenant);
            $occurredAt = $this->occurredAt();
            $actionContext = 'rotation-storage:' . $eventType . ':' . $auditId;
            $resourceContext = 'rotation-storage:scope:' . $scopeHash;
            if ($cursor !== null) {
                $actionContext .= ':' . $cursor;
                $resourceContext = 'rotation-storage:row:' . $scopeHash . ':' . $cursor;
            }
            $actionId = $this->opaqueId(AuditOpaqueIdPurpose::Action, $actionContext);
            $resourceId = $this->opaqueId(AuditOpaqueIdPurpose::Resource, $resourceContext);
            $draft = $this->draft($definition, $actionId, $resourceId, $occurredAt, $tenantReference);
            $request = new AuditDeliveryRequest($draft, 'rotation-storage:' . $actionId);
            $plan = $this->selector()->forDraft($draft);
            $delivery = $this->value('delivery');
            if (!$delivery instanceof AuditDeliveryCoordinator) {
                throw self::failure(AuditDeliveryFailure::StorageUnavailable);
            }
            $delivery->deliverInCurrentTransaction($plan, $request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function catalog(): AuditEventCatalog
    {
        try {
            $catalog = FrameworkOperationalAuditEventCatalog::catalog();
            if (
                !$catalog instanceof AuditEventCatalog
                || $catalog->catalogId !== self::CATALOG_ID
                || $catalog->catalogVersion !== 1
                || $catalog->catalogHash !== self::CATALOG_HASH
                || $catalog->owner !== AuditSourceOwner::Framework
            ) {
                throw new \LogicException();
            }

            return $catalog;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function definition(
        #[\SensitiveParameter]
        AuditEventCatalog $catalog,
        string $eventType,
    ): AuditEventDefinition {
        $expected = $this->expected($eventType);
        try {
            foreach ($catalog->events as $definition) {
                if (
                    !$definition instanceof AuditEventDefinition
                    || $definition->type !== $eventType
                    || $definition->version !== 1
                    || $definition->evidenceVersion !== 1
                    || $definition->category !== AuditEventCategory::Rotation
                    || $definition->actionTypes !== [$eventType]
                    || $definition->evidenceFields !== []
                    || $definition->maxEvidenceBytes !== 13
                    || $definition->delivery !== AuditDelivery::Durable
                    || count($definition->semanticTuples) !== 1
                ) {
                    continue;
                }
                $tuple = $definition->semanticTuples[0];
                if (
                    !$tuple instanceof AuditSemanticTuple
                    || $tuple->actionType !== $eventType
                    || $tuple->phase !== $expected['phase']
                    || $tuple->resourceType !== self::RESOURCE_TYPE
                    || $tuple->outcomeStatus !== $expected['status']
                    || $tuple->outcomeCode !== $expected['outcomeCode']
                    || $tuple->policyDecision !== AuditPolicyDecision::NotApplicable
                    || $tuple->policyReasonCode !== $expected['outcomeCode']
                ) {
                    continue;
                }

                return $definition;
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }

        throw self::failure(AuditDeliveryFailure::PolicyInvalid);
    }

    /** @return array{phase: AuditActionPhase, status: AuditOutcomeStatus, outcomeCode: string} */
    private function expected(string $eventType): array
    {
        return match ($eventType) {
            'rotation.storage.failed' => [
                'phase' => AuditActionPhase::Failed,
                'status' => AuditOutcomeStatus::Failed,
                'outcomeCode' => 'rotation_failed',
            ],
            'rotation.storage.progressed' => [
                'phase' => AuditActionPhase::Committed,
                'status' => AuditOutcomeStatus::Unknown,
                'outcomeCode' => 'rotation_progressed',
            ],
            'rotation.storage.started' => [
                'phase' => AuditActionPhase::Started,
                'status' => AuditOutcomeStatus::Unknown,
                'outcomeCode' => 'rotation_started',
            ],
            'rotation.storage.succeeded' => [
                'phase' => AuditActionPhase::Succeeded,
                'status' => AuditOutcomeStatus::Succeeded,
                'outcomeCode' => 'rotation_succeeded',
            ],
            default => throw new \LogicException(),
        };
    }

    private function assertEventCursor(string $eventType, #[\SensitiveParameter] ?string $cursor): void
    {
        if ($eventType === 'rotation.storage.progressed') {
            if ($cursor === null) {
                throw new \LogicException();
            }
            $this->assertCursor($cursor);

            return;
        }
        if ($cursor !== null) {
            throw new \LogicException();
        }
    }

    private function occurredAt(): string
    {
        $clock = $this->value('clock');
        if (!$clock instanceof ClockInterface) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = $clock
                ->now()
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.u\\Z');
            AuditValidationException::assertSafeString($value, 27, 27);
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{6}Z$/D', $value) !== 1) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ResourceUnavailable);
        }

        return $value;
    }

    private function opaqueId(AuditOpaqueIdPurpose $purpose, #[\SensitiveParameter] string $context): string
    {
        $opaqueIds = $this->value('opaqueIds');
        if (!$opaqueIds instanceof HmacAuditOpaqueIdDeriver) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = (string) $opaqueIds->derive($purpose, $context);
            AuditValidationException::assertSafeString($value, 1, 256);
            return $value;
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::KeyUnavailable);
        }
    }

    private function draft(
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        string $actionId,
        #[\SensitiveParameter]
        string $resourceId,
        #[\SensitiveParameter]
        string $occurredAt,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
    ): AuditRecordDraft {
        try {
            $tuple = $definition->semanticTuples[0] ?? null;
            if (!$tuple instanceof AuditSemanticTuple) {
                throw new \LogicException();
            }
            $buildId = $this->value('buildId');
            $runtime = $this->value('runtime');
            $actor = $this->value('actor');
            $correlation = $this->value('correlation');
            if (
                !is_string($buildId)
                || !$runtime instanceof AuditSourceRuntime
                || !$actor instanceof AuditRecordActor
                || !$correlation instanceof AuditRecordCorrelation
            ) {
                throw new \LogicException();
            }
            AuditValidationException::assertSafeString($buildId, 1, 256);

            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    self::CATALOG_HASH,
                ),
                new AuditRecordAction($actionId, $definition->type, $tuple->phase),
                $actor,
                new AuditRecordResource($tuple->resourceType, $resourceId),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    self::POLICY_ID,
                    1,
                    AuditPolicyDecision::NotApplicable,
                    $tuple->policyReasonCode,
                    AuditDelivery::Durable,
                ),
                $tenant,
                $occurredAt,
                new AuditRecordSource(AuditSourceOwner::Framework, self::COMPONENT, $runtime, $buildId),
                $correlation,
                new AuditRecordEvidence(1),
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function selector(): AuditDeliverySelector
    {
        $selector = $this->value('selector');
        if (!$selector instanceof AuditDeliverySelector) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $selector;
    }

    private function tenantReference(#[\SensitiveParameter] ?TenantRef $tenant): ?AuditTypedReference
    {
        if ($tenant === null) {
            return null;
        }
        $type = $tenant->type();
        $id = $tenant->id();
        if (trim($type) !== $type || trim($id) !== $id) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }

        try {
            return new AuditTypedReference($type, $id);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function reference(?ActorRef $reference): ?AuditTypedReference
    {
        if ($reference === null) {
            return null;
        }

        return new AuditTypedReference($reference->type(), $reference->id());
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== [
                'opaqueIds',
                'selector',
                'delivery',
                'clock',
                'actor',
                'correlation',
                'runtime',
                'buildId',
            ]
            || !$value['opaqueIds'] instanceof HmacAuditOpaqueIdDeriver
            || !$value['selector'] instanceof AuditDeliverySelector
            || !$value['delivery'] instanceof AuditDeliveryCoordinator
            || !$value['clock'] instanceof ClockInterface
            || !$value['actor'] instanceof AuditRecordActor
            || !$value['correlation'] instanceof AuditRecordCorrelation
            || !$value['runtime'] instanceof AuditSourceRuntime
            || !is_string($value['buildId'])
            || !array_key_exists($key, $value)
        ) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            AuditValidationException::assertSafeString($value['buildId'], 1, 256);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }

    private function assertAuditId(#[\SensitiveParameter] string $auditId): void
    {
        AuditValidationException::assertSafeString($auditId, 32, 32);
        if (preg_match('/\A[a-f0-9]{32}\z/D', $auditId) !== 1) {
            throw new \LogicException();
        }
    }

    private function assertScopeHash(#[\SensitiveParameter] string $scopeHash): void
    {
        AuditValidationException::assertSafeString($scopeHash, 64, 64);
        if (preg_match('/\A[a-f0-9]{64}\z/D', $scopeHash) !== 1) {
            throw new \LogicException();
        }
    }

    private function assertCursor(#[\SensitiveParameter] string $cursor): void
    {
        AuditValidationException::assertSafeString($cursor, 1, 128);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $cursor) === 1) {
            try {
                if (Uuid::fromString($cursor)->toRfc4122() !== $cursor) {
                    throw new \LogicException();
                }
            } catch (Throwable) {
                throw new \LogicException();
            }

            return;
        }
        $match = [];
        if (
            preg_match('/\A([1-9][0-9]{0,9}):([a-f0-9]{64})\z/D', $cursor, $match) !== 1
            && preg_match('/\A(2147483647):([a-f0-9]{64})\z/D', $cursor, $match) !== 1
        ) {
            throw new \LogicException();
        }
        if ((int) $match[1] > 2_147_483_647) {
            throw new \LogicException();
        }
    }

    private static function failure(AuditDeliveryFailure $failure): AuditDeliveryException
    {
        return new AuditDeliveryException($failure);
    }

    private static function remap(#[\SensitiveParameter] AuditDeliveryException $failure): AuditDeliveryException
    {
        try {
            return self::failure($failure->failure());
        } catch (Throwable) {
            return self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}
