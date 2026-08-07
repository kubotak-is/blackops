<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationDataReadAuthorizationRequest
{
    public function __construct(
        private OperationDataResource $resource,
        private OperationDataPurpose $purpose,
        private OperationId $operationId,
        private string $operationType,
        private ?ActorRef $currentActor,
        private ?TenantRef $currentTenant,
        private ?ActorRef $originActor,
        private ?TenantRef $originTenant,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $operationType)) {
            throw new InvalidArgumentException('Operation data authorization requires a valid operation type.');
        }
    }

    public function resource(): OperationDataResource
    {
        return $this->resource;
    }

    public function purpose(): OperationDataPurpose
    {
        return $this->purpose;
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

    public function currentTenant(): ?TenantRef
    {
        return $this->currentTenant;
    }

    public function originActor(): ?ActorRef
    {
        return $this->originActor;
    }

    public function originTenant(): ?TenantRef
    {
        return $this->originTenant;
    }
}
