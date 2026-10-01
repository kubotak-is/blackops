<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Throwable;

final readonly class AuthorizationAuditPolicyRegistry
{
    /** @var list<AuthorizationAuditPolicyBinding> */
    private array $bindings;

    /** @param list<AuthorizationAuditPolicyBinding> $bindings */
    public function __construct(#[\SensitiveParameter] array $bindings)
    {
        try {
            if (!array_is_list($bindings)) {
                throw new \LogicException();
            }

            /** @var array<string,true> $definitions */
            $definitions = [];
            /** @var array<string,true> $typeIds */
            $typeIds = [];
            /** @var array<string,string> $policySemantics */
            $policySemantics = [];
            /** @var list<AuthorizationAuditPolicyBinding> $validatedBindings */
            $validatedBindings = [];
            foreach ($bindings as $binding) {
                if (!$binding instanceof AuthorizationAuditPolicyBinding) {
                    throw new \LogicException();
                }

                $this->assertBinding($binding);
                if (array_key_exists($binding->operationClass, $definitions)) {
                    throw new \LogicException();
                }
                if (array_key_exists($binding->operationTypeId, $typeIds)) {
                    throw new \LogicException();
                }

                $definitions[$binding->operationClass] = true;
                $typeIds[$binding->operationTypeId] = true;
                $identity = $binding->policyId . "\x1f" . $binding->policyVersion;
                $semantics = $binding->policyClass . "\x1f" . $binding->delivery->value;
                if (array_key_exists($identity, $policySemantics) && $policySemantics[$identity] !== $semantics) {
                    throw new \LogicException();
                }
                $policySemantics[$identity] = $semantics;
                $validatedBindings[] = $binding;
            }
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->bindings = $validatedBindings;
    }

    public function resolve(#[\SensitiveParameter] OperationMetadata $metadata): AuthorizationAuditPolicyBinding
    {
        try {
            $operationTypeId = $metadata->typeId;
            $operationClass = $metadata->definition;
            $policyClass = $metadata->authorizationPolicy;
            if ($policyClass === null) {
                throw new \LogicException();
            }
            AuditValidationException::assertSafeString($operationTypeId);
            AuditValidationException::assertSafeString($operationClass);
            AuditValidationException::assertSafeString($policyClass);

            foreach ($this->bindings as $binding) {
                if (
                    [$binding->operationTypeId, $binding->operationClass, $binding->policyClass] === [
                        $operationTypeId,
                        $operationClass,
                        $policyClass,
                    ]
                ) {
                    return $binding;
                }
            }
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
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

    private function assertBinding(#[\SensitiveParameter] AuthorizationAuditPolicyBinding $binding): void
    {
        AuditValidationException::assertSafeString($binding->operationTypeId);
        AuditValidationException::assertSafeString($binding->operationClass);
        AuditValidationException::assertSafeString($binding->policyClass);
        AuditValidationException::assertSafeString($binding->policyId);
        if (
            $binding->policyVersion < 1
            || $binding->policyVersion > AuditValidationException::MAX_SAFE_INTEGER
            || !$binding->delivery instanceof AuditDelivery
        ) {
            throw new \LogicException();
        }
    }
}
