<?php

declare(strict_types=1);

namespace BlackOps\Core\Authorization;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;

#[PublicApi]
final readonly class AuthorizationRequest
{
    public function __construct(
        private Operation $operation,
        private OperationValue $value,
        private ExecutionContext $context,
        private ActorRef $actor,
    ) {
        $authorization = $context->actorContext()?->authorization();

        if (
            $authorization === null
            || $authorization->id() !== $actor->id()
            || $authorization->type() !== $actor->type()
        ) {
            throw new InvalidArgumentException('Authorization actor must match the execution context.');
        }
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function value(): OperationValue
    {
        return $this->value;
    }

    public function context(): ExecutionContext
    {
        return $this->context;
    }

    public function actor(): ActorRef
    {
        return $this->actor;
    }
}
