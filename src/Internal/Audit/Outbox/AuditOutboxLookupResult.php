<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

final class AuditOutboxLookupResult
{
    private \SensitiveParameterValue $snapshot;

    private function __construct(#[\SensitiveParameter] bool $found, #[\SensitiveParameter] ?AuditOutboxRecord $record)
    {
        $this->snapshot = new \SensitiveParameterValue([
            'found' => $found,
            'record' => $record,
        ]);
    }

    public static function notFound(): self
    {
        return new self(false, null);
    }

    public static function fromRecord(#[\SensitiveParameter] AuditOutboxRecord $record): self
    {
        return new self(true, $record);
    }

    public function isFound(): bool
    {
        $value = $this->value('found');
        if (!is_bool($value)) {
            throw self::integrity();
        }

        return $value;
    }

    public function record(): ?AuditOutboxRecord
    {
        $record = $this->value('record');
        if ($record !== null && !$record instanceof AuditOutboxRecord) {
            throw self::integrity();
        }
        $found = $this->isFound();
        if ($found && $record === null || !$found && $record !== null) {
            throw self::integrity();
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
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::integrity();
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw self::integrity();
        }
        if (!is_array($value) || !array_key_exists($key, $value)) {
            throw self::integrity();
        }

        return $value[$key];
    }

    private static function integrity(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
    }
}
