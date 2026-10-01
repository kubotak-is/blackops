<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Retention;

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
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
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
use Throwable;

/**
 * Projects a native retention purge effect into one durable audit event.
 *
 * The binding is reduced at construction and the delivery coordinator only
 * receives a request for the transaction already owned by the caller.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class RetentionPurgeAuditWriter
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string CATALOG_ID = 'blackops.framework.operations';
    private const string COMPONENT = 'blackops.retention.purge';
    private const string EVENT_TYPE = 'retention.purge.committed';
    private const string OUTCOME_CODE = 'retention_purged';
    private const string POLICY_ID = 'blackops.retention.purge';

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

    public function committed(
        #[\SensitiveParameter]
        RetentionPurgeAuditId $auditId,
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        RetentionPurgeTarget $target,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        $this->write($auditId, $operationId, $target, $tenant);
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

    private function write(
        #[\SensitiveParameter]
        RetentionPurgeAuditId $auditId,
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        RetentionPurgeTarget $target,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): void {
        try {
            $this->value('buildId');
            $audit = $auditId->toString();
            $operation = $operationId->toString();
            AuditValidationException::assertSafeString($audit, 36, 36);
            AuditValidationException::assertSafeString($operation, 36, 36);
            if (
                RetentionPurgeAuditId::fromString($audit)->toString() !== $audit
                || OperationId::fromString($operation)->toString() !== $operation
                || RetentionPurgeTarget::from($target->value) !== $target
            ) {
                throw new \LogicException();
            }
            $catalog = $this->catalog();
            $definition = $this->definition($catalog);
            $tenantReference = $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id());
            $occurredAt = $this->occurredAt();
            $actionId = $this->opaqueId(
                AuditOpaqueIdPurpose::Action,
                'retention-purge:' . self::EVENT_TYPE . ':' . $audit,
            );
            $resourceId = $this->opaqueId(
                AuditOpaqueIdPurpose::Resource,
                'retention-purge:' . $target->value . ':' . $operation,
            );
            $draft = $this->draft($definition, $actionId, $resourceId, $occurredAt, $tenantReference);
            $request = new AuditDeliveryRequest($draft, 'retention-purge:' . $actionId);
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

    private function definition(#[\SensitiveParameter] AuditEventCatalog $catalog): AuditEventDefinition
    {
        try {
            foreach ($catalog->events as $definition) {
                if (
                    !$definition instanceof AuditEventDefinition
                    || $definition->type !== self::EVENT_TYPE
                    || $definition->version !== 1
                    || $definition->evidenceVersion !== 1
                    || $definition->category !== AuditEventCategory::Retention
                    || $definition->actionTypes !== [self::EVENT_TYPE]
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
                    || $tuple->actionType !== self::EVENT_TYPE
                    || $tuple->phase !== AuditActionPhase::Committed
                    || $tuple->resourceType !== 'operation_data'
                    || $tuple->outcomeStatus !== AuditOutcomeStatus::Succeeded
                    || $tuple->outcomeCode !== self::OUTCOME_CODE
                    || $tuple->policyDecision !== AuditPolicyDecision::NotApplicable
                    || $tuple->policyReasonCode !== self::OUTCOME_CODE
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
            return (string) $opaqueIds->derive($purpose, $context);
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
