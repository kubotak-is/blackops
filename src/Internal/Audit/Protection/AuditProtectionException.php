<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use RuntimeException;

final class AuditProtectionException extends RuntimeException
{
    private function __construct()
    {
        parent::__construct('Audit protected data is unavailable.', 0, null);
    }

    public static function unavailable(): self
    {
        return new self();
    }

    public function failure(): AuditProtectionFailure
    {
        return AuditProtectionFailure::ProtectionFailed;
    }
}
