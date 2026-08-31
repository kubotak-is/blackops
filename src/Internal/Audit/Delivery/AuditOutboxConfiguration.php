<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

final readonly class AuditOutboxConfiguration
{
    public function __construct(
        public int $leaseDurationSeconds = 300,
        public int $maxAttempts = 5,
        public int $backoffBaseSeconds = 1,
        public int $backoffCapSeconds = 3600,
    ) {
        if (
            $leaseDurationSeconds < 1
            || $leaseDurationSeconds > 86_400
            || $maxAttempts < 1
            || $maxAttempts > 32
            || $backoffBaseSeconds < 1
            || $backoffBaseSeconds > 3_600
            || $backoffCapSeconds < 1
            || $backoffCapSeconds > 86_400
        ) {
            throw new AuditOutboxException(AuditOutboxFailure::ConfigurationInvalid);
        }
    }
}
