<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

interface OperationDataSubjectReader
{
    public function findSubject(OperationId $operationId, ?TenantRef $tenant): ?OperationDataSubject;
}
