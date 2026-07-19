<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;

#[PublicApi]
interface OperationStatusQuery
{
    public function find(OperationId $operationId, ?ActorRef $currentActor = null): OperationStatusResult;
}
