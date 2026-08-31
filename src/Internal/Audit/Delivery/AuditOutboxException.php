<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

use RuntimeException;

final class AuditOutboxException extends RuntimeException
{
    public function __construct(
        private readonly AuditOutboxFailure $failure,
    ) {
        parent::__construct('Audit outbox state transition failed.', 0, null);
    }

    public function failure(): AuditOutboxFailure
    {
        return $this->failure;
    }

    public function code(): string
    {
        return $this->failure->value;
    }
}
