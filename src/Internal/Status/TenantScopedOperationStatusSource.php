<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

interface TenantScopedOperationStatusSource extends OperationStatusSource
{
    public function findSubjectForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationStatusSubject;

    public function readDetailForTenant(
        #[\SensitiveParameter]
        OperationStatusSubject $subject,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): OperationStatusDetailResult;
}
