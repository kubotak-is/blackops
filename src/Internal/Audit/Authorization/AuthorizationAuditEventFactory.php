<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

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
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Projects one typed authorization decision into the fixed Framework event.
 *
 * The factory retains only its trusted construction dependencies. The context
 * and evaluation are reduced to the bounded draft before they leave create().
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final class AuthorizationAuditEventFactory
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        Uuidv7Generator $identifiers,
        #[\SensitiveParameter]
        ClockInterface $clock,
        AuditSourceRuntime $runtime,
        #[\SensitiveParameter]
        string $buildId,
        #[\SensitiveParameter]
        ActorRef $executionFallback,
    ) {
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
            new AuditTypedReference($executionFallback->type(), $executionFallback->id());
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->snapshot = new \SensitiveParameterValue([
            'opaqueIds' => $opaqueIds,
            'identifiers' => $identifiers,
            'clock' => $clock,
            'runtime' => $runtime,
            'buildId' => $buildId,
            'executionFallback' => $executionFallback,
        ]);
    }

    public function create(
        #[\SensitiveParameter]
        AuthorizationAuditEvaluation $evaluation,
        #[\SensitiveParameter]
        ExecutionContext $context,
    ): ?AuditDeliveryRequest {
        try {
            $this->value('buildId');
            if ($this->noPolicy($evaluation)) {
                return null;
            }

            $policy = $this->policy($evaluation);
            $actionUuid = $this->actionUuid();
            $occurredAt = $this->occurredAt();
            $catalog = $this->catalog();
            $definition = $this->definition($catalog, $policy);
            $actor = $this->actor($context);
            $tenant = $this->tenant($context);
            $correlation = $this->correlation($context);
            $actionId = $this->opaqueId(AuditOpaqueIdPurpose::Action, 'operation-authorization:' . $actionUuid);
            $resourceId = $this->opaqueId(AuditOpaqueIdPurpose::Resource, $this->operationId($context)->toString());
            $buildId = $this->buildId();
            $draft = $this->draft(
                $catalog,
                $definition,
                $policy,
                $actor,
                $tenant,
                $correlation,
                $actionId,
                $resourceId,
                $occurredAt,
                $buildId,
            );
            $key = $policy->auditDelivery === AuditDelivery::Durable ? 'operation-authorization:' . $actionId : null;

            return new AuditDeliveryRequest($draft, $key);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
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
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    private function noPolicy(#[\SensitiveParameter] AuthorizationAuditEvaluation $evaluation): bool
    {
        try {
            if ($evaluation->hasPolicy()) {
                return false;
            }
            if (
                $evaluation->isAllowed()
                || $evaluation->isDenied()
                || $evaluation->rejection() !== null
                || $evaluation->policy() !== null
            ) {
                throw new \LogicException();
            }

            return true;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function policy(#[\SensitiveParameter] AuthorizationAuditEvaluation $evaluation): AuditRecordPolicy
    {
        try {
            $allowed = $evaluation->isAllowed();
            $denied = $evaluation->isDenied();
            if ($allowed === $denied || !$evaluation->hasPolicy()) {
                throw new \LogicException();
            }
            $policy = $evaluation->policy();
            if (!$policy instanceof AuditRecordPolicy) {
                throw new \LogicException();
            }
            AuditValidationException::assertSafeString($policy->id, 1, 256);
            if (
                $policy->version < 1
                || $policy->version > AuditValidationException::MAX_SAFE_INTEGER
                || !$policy->auditDelivery instanceof AuditDelivery
            ) {
                throw new \LogicException();
            }

            $rejection = $evaluation->rejection();
            if ($allowed) {
                if (
                    $rejection !== null
                    || $policy->decision !== AuditPolicyDecision::Allow
                    || $policy->reasonCode !== 'authorization_allowed'
                ) {
                    throw new \LogicException();
                }
            }
            if (!$allowed) {
                if (
                    !$rejection instanceof RejectionReason
                    || $policy->decision !== AuditPolicyDecision::Deny
                    || $policy->reasonCode !== 'authorization_denied'
                ) {
                    throw new \LogicException();
                }
                $category = $rejection->category();
                $code = $rejection->code();
                if (
                    !$category instanceof RejectionCategory
                    || $category !== RejectionCategory::Unauthorized && $category !== RejectionCategory::Forbidden
                    || !is_string($code)
                ) {
                    throw new \LogicException();
                }
            }

            return new AuditRecordPolicy(
                $policy->id,
                $policy->version,
                $policy->decision,
                $policy->reasonCode,
                $policy->auditDelivery,
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function actionUuid(): string
    {
        $identifiers = $this->value('identifiers');
        if (!$identifiers instanceof Uuidv7Generator) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = $identifiers->generate();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ResourceUnavailable);
        }
        if (
            !is_string($value)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1
        ) {
            throw self::failure(AuditDeliveryFailure::ConfigurationInvalid);
        }

        return $value;
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

    private function catalog(): AuditEventCatalog
    {
        try {
            $catalog = FrameworkAuditEventCatalog::catalog();
            if (!$catalog instanceof AuditEventCatalog) {
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
        #[\SensitiveParameter]
        AuditRecordPolicy $policy,
    ): AuditEventDefinition {
        $allow = $policy->decision === AuditPolicyDecision::Allow;
        $type = $allow ? 'security.authorization.allow' : 'security.authorization.deny';
        $version = $policy->auditDelivery === AuditDelivery::Required ? 2 : 1;
        $phase = $allow ? AuditActionPhase::Succeeded : AuditActionPhase::Denied;
        $status = $allow ? AuditOutcomeStatus::Succeeded : AuditOutcomeStatus::Denied;
        $outcomeCode = $allow ? 'authorization_allowed' : 'authorization_denied';
        $decision = $allow ? AuditPolicyDecision::Allow : AuditPolicyDecision::Deny;

        try {
            foreach ($catalog->events as $definition) {
                if (
                    !$definition instanceof AuditEventDefinition
                    || $definition->type !== $type
                    || $definition->version !== $version
                    || $definition->evidenceVersion !== $version
                    || $definition->category !== AuditEventCategory::Security
                    || $definition->delivery !== $policy->auditDelivery
                    || $definition->actionTypes !== [$type]
                    || count($definition->semanticTuples) !== 1
                    || $definition->evidenceFields !== []
                    || $definition->maxEvidenceBytes !== 13
                ) {
                    continue;
                }
                $tuple = $definition->semanticTuples[0];
                if (
                    $tuple->actionType === $type
                    && $tuple->phase === $phase
                    && $tuple->resourceType === 'operation'
                    && $tuple->outcomeStatus === $status
                    && $tuple->outcomeCode === $outcomeCode
                    && $tuple->policyDecision === $decision
                    && $tuple->policyReasonCode === $outcomeCode
                ) {
                    return $definition;
                }
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }

        throw self::failure(AuditDeliveryFailure::PolicyInvalid);
    }

    private function actor(#[\SensitiveParameter] ExecutionContext $context): AuditRecordActor
    {
        try {
            $actorContext = $context->actorContext();
            if ($actorContext !== null && !$actorContext instanceof ActorContext) {
                throw new \LogicException();
            }
            $principal = $actorContext?->authorization();
            $execution = $actorContext?->execution();
            if ($principal !== null && !$principal instanceof ActorRef) {
                throw new \LogicException();
            }
            if ($execution !== null && !$execution instanceof ActorRef) {
                throw new \LogicException();
            }
            $execution ??= $this->executionFallback();

            return new AuditRecordActor(
                $this->reference($principal),
                $this->reference($execution) ?? throw new \LogicException(),
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function tenant(#[\SensitiveParameter] ExecutionContext $context): ?AuditTypedReference
    {
        try {
            $tenant = $context->tenant();
            if ($tenant === null) {
                return null;
            }
            if (!$tenant instanceof \BlackOps\Core\TenantRef) {
                throw new \LogicException();
            }

            return $this->reference($tenant);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function reference(
        #[\SensitiveParameter]
        ActorRef|\BlackOps\Core\TenantRef|null $reference,
    ): ?AuditTypedReference {
        if ($reference === null) {
            return null;
        }
        try {
            return new AuditTypedReference($reference->type(), $reference->id());
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function correlation(#[\SensitiveParameter] ExecutionContext $context): AuditRecordCorrelation
    {
        try {
            $operationId = $this->operationId($context);
            $correlationId = $context->correlationId();
            $causationId = $context->causationId();
            if (
                !$correlationId instanceof CorrelationId
                || $causationId !== null && !$causationId instanceof CausationId
            ) {
                throw new \LogicException();
            }

            return AuditRecordCorrelation::fromArray([
                'operationId' => $operationId->toString(),
                'correlationId' => $correlationId->toString(),
                'causationId' => $causationId?->toString(),
            ]);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function operationId(#[\SensitiveParameter] ExecutionContext $context): OperationId
    {
        try {
            $operationId = $context->operationId();
            if (!$operationId instanceof OperationId) {
                throw new \LogicException();
            }

            return $operationId;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
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

    private function executionFallback(): ActorRef
    {
        $fallback = $this->value('executionFallback');
        if (!$fallback instanceof ActorRef) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $fallback;
    }

    private function buildId(): string
    {
        $buildId = $this->value('buildId');
        if (!is_string($buildId)) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $buildId;
    }

    /**
     * @param AuditRecordActor $actor
     * @param AuditTypedReference|null $tenant
     * @mago-expect lint:excessive-parameter-list
     */
    private function draft(
        #[\SensitiveParameter]
        AuditEventCatalog $catalog,
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        AuditRecordPolicy $policy,
        #[\SensitiveParameter]
        AuditRecordActor $actor,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        AuditRecordCorrelation $correlation,
        #[\SensitiveParameter]
        string $actionId,
        #[\SensitiveParameter]
        string $resourceId,
        #[\SensitiveParameter]
        string $occurredAt,
        #[\SensitiveParameter]
        string $buildId,
    ): AuditRecordDraft {
        try {
            $tuple = $definition->semanticTuples[0] ?? null;
            if (!$tuple instanceof \BlackOps\Core\Audit\AuditSemanticTuple) {
                throw new \LogicException();
            }

            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    $catalog->catalogHash,
                ),
                new AuditRecordAction($actionId, $definition->type, $tuple->phase),
                $actor,
                new AuditRecordResource($tuple->resourceType, $resourceId),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                $policy,
                $tenant,
                $occurredAt,
                new AuditRecordSource(
                    AuditSourceOwner::Framework,
                    'operation-authorization',
                    $this->runtime(),
                    $buildId,
                ),
                $correlation,
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function runtime(): AuditSourceRuntime
    {
        $runtime = $this->value('runtime');
        if (!$runtime instanceof AuditSourceRuntime) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $runtime;
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
            || array_keys($value) !== ['opaqueIds', 'identifiers', 'clock', 'runtime', 'buildId', 'executionFallback']
            || !$value['opaqueIds'] instanceof HmacAuditOpaqueIdDeriver
            || !$value['identifiers'] instanceof Uuidv7Generator
            || !$value['clock'] instanceof ClockInterface
            || !$value['runtime'] instanceof AuditSourceRuntime
            || !is_string($value['buildId'])
            || !$value['executionFallback'] instanceof ActorRef
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
