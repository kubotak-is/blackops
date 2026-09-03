<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;

final readonly class AuditProtectedReadGate
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
            $this->delivery->deliverStandalone(new AuditDeliveryRequest($draft, null));
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        $decision = $context->authorizationDecision;
        try {
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
            $this->delivery->deliverStandalone(new AuditDeliveryRequest($draft, null));

            return $decision;
        } catch (AuditDeliveryException $failure) {
            if ($decision === AuditReadAuthorizationDecision::Allow) {
                throw $failure;
            }

            return $decision;
        } catch (\Throwable) {
            if ($decision === AuditReadAuthorizationDecision::Allow) {
                throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
            }

            return $decision;
        }
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
        if (
            $draft->event->type !== $expectedType
            || $draft->action->type !== $expectedType
            || $draft->action->id !== $basis->requestRef()
            || $draft->actor->principal?->toArray() !== $basis->actor?->toArray()
            || $actor->execution->toArray() !== $basis->execution->toArray()
            || $draft->tenant?->toArray() !== $basis->tenant?->toArray()
            || $draft->occurredAt !== $basis->requestedAt
            || $draft->policy->auditDelivery->value !== 'required'
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
