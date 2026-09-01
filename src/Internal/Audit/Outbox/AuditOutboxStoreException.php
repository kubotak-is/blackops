<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use RuntimeException;

final class AuditOutboxStoreException extends RuntimeException
{
    public function __construct(
        private readonly AuditOutboxStoreFailure $storeFailure,
    ) {
        parent::__construct('Audit outbox persistence failed.', 0, null);
    }

    public function failure(): AuditOutboxStoreFailure
    {
        return $this->storeFailure;
    }

    public function code(): string
    {
        return $this->storeFailure->value;
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
        throw new self(AuditOutboxStoreFailure::IntegrityInvalid);
    }
}
