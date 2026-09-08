<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

final readonly class TenantBoundOperationStatusSource implements OperationStatusSource
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        TenantScopedOperationStatusSource $source,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'source' => $source,
            'tenant' => $tenant,
        ]);
    }

    public function findSubject(#[\SensitiveParameter] OperationId $operationId): ?OperationStatusSubject
    {
        $values = $this->values();

        return $values['source']->findSubjectForTenant($operationId, $values['tenant']);
    }

    public function readDetail(#[\SensitiveParameter] OperationStatusSubject $subject): OperationStatusDetailResult
    {
        $values = $this->values();

        return $values['source']->readDetailForTenant($subject, $values['tenant']);
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
        throw OperationStatusSourceException::storageFailed();
    }

    /** @return array{source: TenantScopedOperationStatusSource, tenant: TenantRef|null} */
    private function values(): array
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw OperationStatusSourceException::storageFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['source', 'tenant']
            || !$value['source'] instanceof TenantScopedOperationStatusSource
            || $value['tenant'] !== null && !$value['tenant'] instanceof TenantRef
        ) {
            throw OperationStatusSourceException::storageFailed();
        }

        return [
            'source' => $value['source'],
            'tenant' => $value['tenant'],
        ];
    }
}
