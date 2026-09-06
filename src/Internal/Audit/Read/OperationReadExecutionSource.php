<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Read;

use BlackOps\Core\Audit\AuditTypedReference;

interface OperationReadExecutionSource
{
    public function current(): AuditTypedReference;
}
