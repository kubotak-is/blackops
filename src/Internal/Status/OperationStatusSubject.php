<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use InvalidArgumentException;

final readonly class OperationStatusSubject
{
    public function __construct(
        public OperationId $operationId,
        public string $operationType,
        public ?ActorRef $originActor,
        public ?TenantRef $tenant = null,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $operationType)) {
            throw new InvalidArgumentException('Operation status subject requires a valid operation type.');
        }
    }
}
