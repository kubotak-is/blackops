<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Throwable;

final readonly class AuthorizationAuditPolicyBinding
{
    public string $operationTypeId;
    public string $operationClass;
    public string $policyClass;
    public string $policyId;
    public int $policyVersion;
    public AuditDelivery $delivery;

    public function __construct(
        #[\SensitiveParameter]
        OperationMetadata $metadata,
        #[\SensitiveParameter]
        string $policyId,
        #[\SensitiveParameter]
        int $policyVersion,
        AuditDelivery $delivery,
    ) {
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
            AuditValidationException::assertSafeString($policyId);
            if ($policyVersion < 1 || $policyVersion > AuditValidationException::MAX_SAFE_INTEGER) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->operationTypeId = $operationTypeId;
        $this->operationClass = $operationClass;
        $this->policyClass = $policyClass;
        $this->policyId = $policyId;
        $this->policyVersion = $policyVersion;
        $this->delivery = $delivery;
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
