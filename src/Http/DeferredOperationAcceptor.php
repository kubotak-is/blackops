<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;

interface DeferredOperationAcceptor
{
    public function accepts(Operation $definition): bool;

    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): DeferredAcknowledgement|OperationResult;
}
