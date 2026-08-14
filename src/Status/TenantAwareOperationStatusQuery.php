<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

#[PublicApi]
interface TenantAwareOperationStatusQuery extends OperationStatusQuery
{
    public function findForTenant(
        OperationId $operationId,
        ?ActorRef $currentActor = null,
        ?TenantRef $currentTenant = null,
    ): OperationStatusResult;
}
