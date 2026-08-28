<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final class AuditProjectionException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(AuditProjectionFailure::ProjectionInvalid->value, 0, null);
    }

    public function code(): string
    {
        return AuditProjectionFailure::ProjectionInvalid->value;
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
}
