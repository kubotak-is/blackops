<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use BlackOps\Internal\Audit\Record\CompleteAuditRecord;

final readonly class AuditLookupResult
{
    private \SensitiveParameterValue $snapshot;

    private function __construct(bool $found, #[\SensitiveParameter] ?CompleteAuditRecord $record)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'found' => $found,
            'record' => $record,
        ]);
    }

    public static function fromRecord(#[\SensitiveParameter] CompleteAuditRecord $record): self
    {
        return new self(true, $record);
    }

    public static function notFound(): self
    {
        return new self(false, null);
    }

    public function isFound(): bool
    {
        $found = $this->value('found');
        if (!is_bool($found)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $found;
    }

    public function record(): ?CompleteAuditRecord
    {
        $record = $this->value('record');
        if ($record !== null && !$record instanceof CompleteAuditRecord) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $found = $this->isFound();
        if ($found && $record === null || !$found && $record !== null) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $record;
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
