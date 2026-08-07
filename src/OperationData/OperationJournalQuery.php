<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

#[PublicApi]
interface OperationJournalQuery
{
    public function records(
        OperationId $operationId,
        ?ActorRef $currentActor,
        ?TenantRef $currentTenant,
        OperationDataPurpose $purpose,
    ): OperationJournalReadResult;
}
