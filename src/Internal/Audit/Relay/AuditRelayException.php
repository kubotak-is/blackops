<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

use RuntimeException;

final class AuditRelayException extends RuntimeException
{
    public function __construct(
        private readonly AuditRelayFailure $failure,
    ) {
        parent::__construct('Audit relay operation failed.', 0, null);
    }

    public function failure(): AuditRelayFailure
    {
        return $this->failure;
    }

    public function code(): string
    {
        return $this->failure->value;
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
        throw new self(AuditRelayFailure::StorageUnavailable);
    }
}
