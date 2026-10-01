<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Throwable;

final readonly class AuthorizationAuditEvaluation
{
    private const int NO_POLICY = 0;
    private const int ALLOWED = 1;
    private const int DENIED = 2;
    private const string ALLOWED_REASON = 'authorization_allowed';
    private const string DENIED_REASON = 'authorization_denied';

    private function __construct(
        private int $state,
        #[\SensitiveParameter]
        private ?RejectionReason $rejection,
        private ?AuditRecordPolicy $policy,
    ) {}

    public static function noPolicy(): self
    {
        return new self(self::NO_POLICY, null, null);
    }

    public static function fromDecision(
        #[\SensitiveParameter]
        AuthorizationAuditPolicyBinding $binding,
        #[\SensitiveParameter]
        ?RejectionReason $rejection,
    ): self {
        try {
            $decision = AuditPolicyDecision::Allow;
            $reasonCode = self::ALLOWED_REASON;
            $state = self::ALLOWED;
            if ($rejection !== null) {
                $category = $rejection->category();
                if (
                    !$category instanceof RejectionCategory
                    || $category !== RejectionCategory::Unauthorized && $category !== RejectionCategory::Forbidden
                ) {
                    throw new \LogicException();
                }
                if (!is_string($rejection->code())) {
                    throw new \LogicException();
                }
                $decision = AuditPolicyDecision::Deny;
                $reasonCode = self::DENIED_REASON;
                $state = self::DENIED;
            }

            $policy = new AuditRecordPolicy(
                $binding->policyId,
                $binding->policyVersion,
                $decision,
                $reasonCode,
                $binding->delivery,
            );
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }

        return new self($state, $rejection, $policy);
    }

    public function hasPolicy(): bool
    {
        return $this->state !== self::NO_POLICY;
    }

    public function isAllowed(): bool
    {
        return $this->state === self::ALLOWED;
    }

    public function isDenied(): bool
    {
        return $this->state === self::DENIED;
    }

    public function rejection(): ?RejectionReason
    {
        return $this->rejection;
    }

    public function policy(): ?AuditRecordPolicy
    {
        return $this->policy;
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
        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
    }
}
