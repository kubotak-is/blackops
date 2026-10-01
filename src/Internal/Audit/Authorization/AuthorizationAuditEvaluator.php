<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use Throwable;

final class AuthorizationAuditEvaluator
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuthorizationEvaluator $evaluator,
        #[\SensitiveParameter]
        AuthorizationAuditPolicyRegistry $policies,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'evaluator' => $evaluator,
            'policies' => $policies,
        ]);
    }

    public function evaluate(
        #[\SensitiveParameter]
        OperationMetadata $metadata,
        #[\SensitiveParameter]
        OperationEnvelope $envelope,
    ): AuthorizationAuditEvaluation {
        try {
            if (get_class($envelope->definition()) !== $metadata->definition) {
                throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
            }
            $policyClass = $metadata->authorizationPolicy;
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }

        $evaluator = $this->value('evaluator');
        $policies = $this->value('policies');
        if ($policyClass === null) {
            return AuthorizationAuditEvaluation::noPolicy();
        }
        if (!$policies instanceof AuthorizationAuditPolicyRegistry) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $binding = $policies->resolve($metadata);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        if (!$evaluator instanceof AuthorizationEvaluator) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $rejection = $evaluator->evaluate($metadata, $envelope);
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }

        return AuthorizationAuditEvaluation::fromDecision($binding, $rejection);
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

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['evaluator', 'policies']
            || !$value['evaluator'] instanceof AuthorizationEvaluator
            || !$value['policies'] instanceof AuthorizationAuditPolicyRegistry
            || !array_key_exists($key, $value)
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }
}
