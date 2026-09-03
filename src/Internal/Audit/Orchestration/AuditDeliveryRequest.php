<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditValidationException;

final class AuditDeliveryRequest
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        ?string $idempotencyKey,
    ) {
        if ($idempotencyKey !== null) {
            try {
                AuditValidationException::assertSafeString($idempotencyKey, 1, 256);
            } catch (\Throwable) {
                throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
            }
        }

        $this->snapshot = new \SensitiveParameterValue([
            'draft' => $draft,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function draft(): AuditRecordDraft
    {
        $value = $this->value('draft');
        if (!$value instanceof AuditRecordDraft) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value;
    }

    public function idempotencyKey(): ?string
    {
        $value = $this->value('idempotencyKey');
        if ($value !== null && !is_string($value)) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
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
        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        if (!is_array($value) || !array_key_exists($key, $value)) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }
}
