<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Outcome\OutcomeRecord;

interface TenantScopedOutcomeReader
{
    public function findForTenant(OperationId $operationId, ?TenantRef $tenant): ?OutcomeRecord;
}
