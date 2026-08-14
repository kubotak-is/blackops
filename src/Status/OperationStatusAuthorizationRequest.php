<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationStatusAuthorizationRequest
{
    public function __construct(
        private OperationId $operationId,
        private string $operationType,
        private ?ActorRef $currentActor,
        private ?ActorRef $originActor,
        private ?TenantRef $currentTenant = null,
        private ?TenantRef $originTenant = null,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $operationType)) {
            throw new InvalidArgumentException('Operation status authorization requires a valid operation type.');
        }
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function operationType(): string
    {
        return $this->operationType;
    }

    public function currentActor(): ?ActorRef
    {
        return $this->currentActor;
    }

    public function originActor(): ?ActorRef
    {
        return $this->originActor;
    }

    public function currentTenant(): ?TenantRef
    {
        return $this->currentTenant;
    }

    public function originTenant(): ?TenantRef
    {
        return $this->originTenant;
    }
}
