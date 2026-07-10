<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;

interface DeferredOperationAcceptor
{
    public function accepts(Operation $definition): bool;

    public function accept(Operation $definition, OperationValue $value): DeferredAcknowledgement;
}
