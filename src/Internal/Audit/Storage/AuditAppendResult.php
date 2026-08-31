<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use BlackOps\Internal\Audit\Protection\AuditStoredRecord;

final readonly class AuditAppendResult
{
    /**
     * @param AuditStoredRecord $record
     */
    public function __construct(#[\SensitiveParameter] AuditStoredRecord $record, bool $inserted)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'record' => $record,
            'inserted' => $inserted,
        ]);
    }

    private \SensitiveParameterValue $snapshot;

    public function record(): AuditStoredRecord
    {
        $record = $this->value('record');
        if (!$record instanceof AuditStoredRecord) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $record;
    }

    public function inserted(): bool
    {
        $inserted = $this->value('inserted');
        if (!is_bool($inserted)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $inserted;
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
    public function __unserialize(array $data): void
    {
        throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
    }

    private function value(string $key): mixed
    {
        try {
            $snapshot = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if (!is_array($snapshot) || !array_key_exists($key, $snapshot)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $snapshot[$key];
    }
}
