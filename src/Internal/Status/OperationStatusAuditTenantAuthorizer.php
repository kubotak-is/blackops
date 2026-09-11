<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\TenantRef;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class OperationStatusAuditTenantAuthorizer implements OperationStatusAuthorizer
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] OperationStatusAuthorizer $authorizer)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'authorizer' => $authorizer,
        ]);
    }

    public function decide(
        #[\SensitiveParameter]
        OperationStatusAuthorizationRequest $request,
    ): OperationStatusAuthorizationDecision {
        if (!$this->sameTenant($request->currentTenant(), $request->originTenant())) {
            return OperationStatusAuthorizationDecision::deny();
        }

        $authorizer = $this->value('authorizer');
        if (!$authorizer instanceof OperationStatusAuthorizer) {
            throw OperationStatusQueryException::authorizationFailed();
        }

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
        throw OperationStatusQueryException::authorizationFailed();
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
            throw OperationStatusQueryException::authorizationFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['authorizer']
            || !$value['authorizer'] instanceof OperationStatusAuthorizer
            || !array_key_exists($key, $value)
        ) {
            throw OperationStatusQueryException::authorizationFailed();
        }

        return $value[$key];
    }
}
