<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\TenantRef;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;

final readonly class OperationDataAuditTenantAuthorizer implements OperationDataReadAuthorizer
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] OperationDataReadAuthorizer $authorizer)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'authorizer' => $authorizer,
        ]);
    }

    public function decide(
        #[\SensitiveParameter]
        OperationDataReadAuthorizationRequest $request,
    ): OperationDataReadAuthorizationDecision {
        if (!$this->sameTenant($request->currentTenant(), $request->originTenant())) {
            return OperationDataReadAuthorizationDecision::deny();
        }

        $authorizer = $this->value('authorizer');
        /** @var OperationDataReadAuthorizer $authorizer */
        return $authorizer->decide($request);
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
        throw new \LogicException('Operation data audit authorizer is unavailable.');
    }

    private function sameTenant(
        #[\SensitiveParameter]
        ?TenantRef $current,
        #[\SensitiveParameter]
        ?TenantRef $origin,
    ): bool {
        if ($current === null || $origin === null) {
            return $current === null && $origin === null;
        }

        return $current->type() === $origin->type() && $current->id() === $origin->id();
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new \LogicException('Operation data audit authorizer is unavailable.');
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['authorizer']
            || !$value['authorizer'] instanceof OperationDataReadAuthorizer
            || !array_key_exists($key, $value)
        ) {
            throw new \LogicException('Operation data audit authorizer is unavailable.');
        }

        return $value[$key];
    }
}
