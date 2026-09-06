<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

final class OperationDiagnosticsAuthorizationRequest
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'operationId' => $operationId,
            'currentActor' => $currentActor,
            'currentTenant' => $currentTenant,
        ]);
    }

    public function operationId(): OperationId
    {
        $value = $this->value('operationId');
        if (!$value instanceof OperationId) {
            throw new \LogicException('Operation diagnostics authorization request is unavailable.', 0, null);
        }

        return $value;
    }

    public function currentActor(): ?ActorRef
    {
        $value = $this->value('currentActor');
        if ($value !== null && !$value instanceof ActorRef) {
            throw new \LogicException('Operation diagnostics authorization request is unavailable.', 0, null);
        }

        return $value;
    }

    public function currentTenant(): ?TenantRef
    {
        $value = $this->value('currentTenant');
        if ($value !== null && !$value instanceof TenantRef) {
            throw new \LogicException('Operation diagnostics authorization request is unavailable.', 0, null);
        }

        return $value;
    }

    /** @return array<never,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never,never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new \LogicException('Operation diagnostics authorization request cannot be unserialized.', 0, null);
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new \LogicException('Operation diagnostics authorization request is unavailable.', 0, null);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['operationId', 'currentActor', 'currentTenant']
            || !$value['operationId'] instanceof OperationId
            || $value['currentActor'] !== null && !$value['currentActor'] instanceof ActorRef
            || $value['currentTenant'] !== null && !$value['currentTenant'] instanceof TenantRef
            || !array_key_exists($key, $value)
        ) {
            throw new \LogicException('Operation diagnostics authorization request is unavailable.', 0, null);
        }

        return $value[$key];
    }
}
