<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final class AuditAnchorException extends \RuntimeException
{
    public function __construct(
        private readonly AuditAnchorFailure $failure,
    ) {
        parent::__construct('Audit anchor state transition failed.');
    }

    public function failure(): AuditAnchorFailure
    {
        return $this->failure;
    }

    public function code(): string
    {
        return $this->failure->value;
    }
}
