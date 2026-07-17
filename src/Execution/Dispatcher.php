<?php

declare(strict_types=1);

namespace BlackOps\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;

#[PublicApi]
interface Dispatcher
{
    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): OperationResult;
}
