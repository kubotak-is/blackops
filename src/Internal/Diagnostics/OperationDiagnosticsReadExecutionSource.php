<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Identifier\ValidatedUuidv7Generator;

final readonly class OperationDiagnosticsReadExecutionSource implements OperationReadExecutionSource
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] Uuidv7Generator $identifiers)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'identifiers' => new ValidatedUuidv7Generator($identifiers),
        ]);
    }

    public function current(): AuditTypedReference
    {
        try {
            $identifiers = $this->value('identifiers');
            if (!$identifiers instanceof ValidatedUuidv7Generator) {
                throw new \LogicException();
            }

            return new AuditTypedReference('operation_diagnostics_read', $identifiers->generate());
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }
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
        throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['identifiers']
            || !$value['identifiers'] instanceof ValidatedUuidv7Generator
            || !array_key_exists($key, $value)
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }

        return $value[$key];
    }
}
