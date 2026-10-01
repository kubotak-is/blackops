<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
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
use BlackOps\Core\TenantRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Builds the fixed framework authentication result event and submits it once.
 *
 * The explicit validation and failure branches keep this security boundary
 * visible while preserving the fixed draft and delivery ordering.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final class DurableAuthenticationAuditEventWriter implements AuthenticationAuditEventWriter
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        Uuidv7Generator $executions,
        #[\SensitiveParameter]
        ClockInterface $clock,
        #[\SensitiveParameter]
        string $buildId,
    ) {
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->snapshot = new \SensitiveParameterValue([
            'delivery' => $delivery,
            'opaqueIds' => $opaqueIds,
            'executions' => $executions,
            'clock' => $clock,
            'buildId' => $buildId,
        ]);
    }

    public function write(#[\SensitiveParameter] AuthenticationResult $result): void
    {
        $state = $this->state($result);
        if ($state === self::ANONYMOUS) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }

        $executionId = $this->executionId();
        $occurredAt = $this->occurredAt();
        $catalog = $this->catalog();
        $definition = $this->definition($catalog, $state);
        $actor = $this->actor($result, $state, $executionId);
        $tenant = $this->tenant($result, $state);
        [$actionId, $resourceId] = $this->opaqueIds($executionId);
        $draft = $this->draft($catalog, $definition, $actor, $tenant, $actionId, $resourceId, $occurredAt);
        try {
            $request = new AuditDeliveryRequest($draft, 'http-authentication:' . $actionId);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }

        $delivery = $this->value('delivery');
        if (!$delivery instanceof AuditDeliveryCoordinator) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $delivery->deliverStandalone($request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
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

    private const int ANONYMOUS = 0;
    private const int AUTHENTICATED = 1;
    private const int INVALID = 2;

    private function state(#[\SensitiveParameter] AuthenticationResult $result): int
    {
        try {
            $anonymous = $result->isAnonymous();
            $authenticated = $result->isAuthenticated();
            $invalid = $result->isInvalid();
            if (!is_bool($anonymous) || !is_bool($authenticated) || !is_bool($invalid)) {
                throw new \LogicException();
            }
            if (((int) $anonymous + (int) $authenticated + (int) $invalid) !== 1) {
                throw new \LogicException();
            }

            $actor = $result->actor();
            $tenant = $result->tenant();
            if ($authenticated) {
                if (!$actor instanceof ActorRef || $tenant !== null && !$tenant instanceof TenantRef) {
                    throw new \LogicException();
                }

                return self::AUTHENTICATED;
            }
            if ($actor !== null || $tenant !== null) {
                throw new \LogicException();
            }
            if ($anonymous) {
                return self::ANONYMOUS;
            }

            return self::INVALID;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function executionId(): string
    {
        $executions = $this->value('executions');
        if (!$executions instanceof Uuidv7Generator) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = $executions->generate();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ResourceUnavailable);
        }
        if (!is_string($value)) {
            throw self::failure(AuditDeliveryFailure::ConfigurationInvalid);
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1) {
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
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/D', $value) !== 1) {
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
            return FrameworkAuditEventCatalog::catalog();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function definition(AuditEventCatalog $catalog, int $state): AuditEventDefinition
    {
        $type = $state === self::AUTHENTICATED
            ? 'security.authentication.succeeded'
            : 'security.authentication.invalid';
        foreach ($catalog->events as $definition) {
            if (
                $definition->type === $type
                && $definition->version === 1
                && $definition->evidenceVersion === 1
                && $definition->delivery === AuditDelivery::Durable
                && count($definition->semanticTuples) === 1
            ) {
                return $definition;
            }
        }

        throw self::failure(AuditDeliveryFailure::PolicyInvalid);
    }

    private function actor(
        #[\SensitiveParameter]
        AuthenticationResult $result,
        int $state,
        #[\SensitiveParameter]
        string $executionId,
    ): AuditRecordActor {
        try {
            $actor = $state === self::AUTHENTICATED ? $result->actor() : null;
            $principal = $actor instanceof ActorRef ? new AuditTypedReference($actor->type(), $actor->id()) : null;

            return new AuditRecordActor($principal, new AuditTypedReference('http_authentication', $executionId));
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function tenant(#[\SensitiveParameter] AuthenticationResult $result, int $state): ?AuditTypedReference
    {
        if ($state !== self::AUTHENTICATED) {
            return null;
        }
        try {
            $tenant = $result->tenant();
            if ($tenant === null) {
                return null;
            }
            if (!$tenant instanceof TenantRef) {
                throw new \LogicException();
            }

            return new AuditTypedReference($tenant->type(), $tenant->id());
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /** @return array{0:string,1:string} */
    private function opaqueIds(#[\SensitiveParameter] string $executionId): array
    {
        $opaqueIds = $this->value('opaqueIds');
        if (!$opaqueIds instanceof HmacAuditOpaqueIdDeriver) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        $context = 'http_authentication:' . $executionId;
        try {
            $action = $opaqueIds->derive(AuditOpaqueIdPurpose::Action, $context);
            $resource = $opaqueIds->derive(AuditOpaqueIdPurpose::Resource, $context);
            return [(string) $action, (string) $resource];
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::KeyUnavailable);
        }
    }

    /**
     * Each typed field remains explicit so the fixed draft cannot hide an
     * unvalidated fallback inside a generic payload.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    private function draft(
        AuditEventCatalog $catalog,
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        AuditRecordActor $actor,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $actionId,
        #[\SensitiveParameter]
        string $resourceId,
        #[\SensitiveParameter]
        string $occurredAt,
    ): AuditRecordDraft {
        try {
            $tuple = $definition->semanticTuples[0];
            if (
                $definition->type !== $tuple->actionType
                || $tuple->resourceType !== 'authentication'
                || $tuple->phase
                    !== (
                        $definition->type === 'security.authentication.succeeded'
                            ? AuditActionPhase::Succeeded
                            : AuditActionPhase::Denied
                    )
                || $tuple->outcomeStatus
                    !== (
                        $definition->type === 'security.authentication.succeeded'
                            ? AuditOutcomeStatus::Succeeded
                            : AuditOutcomeStatus::Denied
                    )
                || $tuple->policyDecision
                    !== (
                        $definition->type === 'security.authentication.succeeded'
                            ? AuditPolicyDecision::Allow
                            : AuditPolicyDecision::Deny
                    )
                || $tuple->policyReasonCode
                    !== (
                        $definition->type === 'security.authentication.succeeded'
                            ? 'authenticated'
                            : 'authentication_invalid'
                    )
                || $definition->delivery !== AuditDelivery::Durable
            ) {
                throw new \LogicException();
            }
            $buildId = $this->value('buildId');
            if (!is_string($buildId)) {
                throw new \LogicException();
            }
            AuditValidationException::assertSafeString($buildId, 1, 256);

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
                new AuditRecordPolicy(
                    'http-authentication',
                    1,
                    $tuple->policyDecision,
                    $tuple->policyReasonCode,
                    AuditDelivery::Durable,
                ),
                $tenant,
                $occurredAt,
                new AuditRecordSource(
                    AuditSourceOwner::Framework,
                    'http-authentication',
                    AuditSourceRuntime::Http,
                    $buildId,
                ),
                AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
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
            || array_keys($value) !== ['delivery', 'opaqueIds', 'executions', 'clock', 'buildId']
            || !$value['delivery'] instanceof AuditDeliveryCoordinator
            || !$value['opaqueIds'] instanceof HmacAuditOpaqueIdDeriver
            || !$value['executions'] instanceof Uuidv7Generator
            || !$value['clock'] instanceof ClockInterface
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
