<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;

/**
 * Keeps the complete field-binding matrix in one fail-closed writer boundary.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class AuditProtectedReadGate implements AuditReadEventWriter
{
    public function __construct(
        private AuditDeliveryCoordinator $delivery,
    ) {}

    public function requested(
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): void {
        try {
            $this->assertBinding($basis, $draft, 'requested');
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
        try {
            $this->delivery->deliverStandalone(new AuditDeliveryRequest($draft, null));
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        try {
            $decision = $context->authorizationDecision;
            $basis = $context->basis();
            if ($context->requestRef !== $basis->requestRef()) {
                throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
            }
            $event = match ($decision) {
                AuditReadAuthorizationDecision::Allow => 'accessed',
                AuditReadAuthorizationDecision::Deny,
                AuditReadAuthorizationDecision::ResourceUnavailable,
                    => 'unavailable',
            };
            $this->assertBinding($basis, $draft, $event);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
        try {
            $this->delivery->deliverStandalone(new AuditDeliveryRequest($draft, null));
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $decision;
    }

    private function assertBinding(
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        string $phase,
    ): void {
        $expectedType = 'access.operation_' . $basis->purpose->value . '.' . $phase;
        $actor = $draft->actor;
        $tuple = match ($phase) {
            'requested' => [
                AuditActionPhase::Requested,
                AuditOutcomeStatus::Unknown,
                'read_requested',
                AuditPolicyDecision::NotApplicable,
                'read_requested',
            ],
            'accessed' => [
                AuditActionPhase::Succeeded,
                AuditOutcomeStatus::Succeeded,
                'read_accessed',
                AuditPolicyDecision::Allow,
                'authorization_allowed',
            ],
            'unavailable' => [
                AuditActionPhase::Unknown,
                AuditOutcomeStatus::Unknown,
                'resource_unavailable',
                AuditPolicyDecision::NotApplicable,
                'resource_unavailable',
            ],
            default => throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid),
        };
        try {
            AuditValidationException::assertSafeString($draft->source->buildId, 1, 256);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
        if (
            $draft->event->type !== $expectedType
            || $draft->event->version !== 1
            || $draft->event->category !== AuditEventCategory::Access
            || $draft->event->catalogHash !== 'cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83'
            || $draft->action->type !== $expectedType
            || $draft->action->id !== $basis->requestRef()
            || $draft->action->phase !== $tuple[0]
            || $draft->actor->principal?->toArray() !== $basis->actor?->toArray()
            || $actor->execution->toArray() !== $basis->execution->toArray()
            || $draft->tenant?->toArray() !== $basis->tenant?->toArray()
            || $draft->occurredAt !== $basis->requestedAt
            || $draft->resource->type !== 'operation'
            || preg_match('/^oid:v1:[a-f0-9]{64}$/D', $draft->resource->id) !== 1
            || $draft->outcome->status !== $tuple[1]
            || $draft->outcome->code !== $tuple[2]
            || $draft->policy->id !== 'operation-read'
            || $draft->policy->version !== 1
            || $draft->policy->decision !== $tuple[3]
            || $draft->policy->reasonCode !== $tuple[4]
            || $draft->policy->auditDelivery !== AuditDelivery::Required
            || $draft->source->owner !== AuditSourceOwner::Framework
            || $draft->source->component !== 'operation-' . $basis->purpose->value
            || !in_array(
                $draft->source->runtime->value,
                ['http', 'cli', 'worker', 'scheduler', 'relay', 'replay'],
                strict: true,
            )
            || $draft->correlation->toArray() !== []
            || $draft->evidence->version !== 1
            || $draft->evidence->fields !== []
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
