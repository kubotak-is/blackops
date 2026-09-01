<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

final class AuditOutboxAppendResult
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] AuditOutboxRecord $record, #[\SensitiveParameter] bool $inserted)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'record' => $record,
            'inserted' => $inserted,
        ]);
    }

    public function record(): AuditOutboxRecord
    {
        $value = $this->value('record');
        if (!$value instanceof AuditOutboxRecord) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    public function inserted(): bool
    {
        $value = $this->value('inserted');
        if (!is_bool($value)) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }
        if (!is_array($value) || !array_key_exists($key, $value)) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }

        return $value[$key];
    }
}
