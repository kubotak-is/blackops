<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;

final readonly class OperationDataSubject
{
    public function __construct(
        public OperationId $operationId,
        public string $operationType,
        public ?ActorRef $originActor,
        public ?TenantRef $originTenant,
    ) {}
}
