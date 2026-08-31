<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use RuntimeException;

final class AuditStoreException extends RuntimeException
{
    public function __construct(
        private AuditStoreFailure $storeFailure,
    ) {
        parent::__construct('Audit store operation failed.', 0, null);
    }

    public function failure(): AuditStoreFailure
    {
        return $this->storeFailure;
    }

    public function code(): string
    {
        return $this->storeFailure->value;
    }
}
