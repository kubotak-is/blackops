<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;

interface ScheduledDeferredAcceptor
{
    public function accept(
        DeferredOperationMessage $message,
        OperationEnvelope $envelope,
        OperationMetadata $metadata,
    ): DeferredAcknowledgement|OperationResult;
}
