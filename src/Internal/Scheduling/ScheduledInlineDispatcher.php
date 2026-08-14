<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;

interface ScheduledInlineDispatcher
{
    public function dispatchScheduled(
        OperationEnvelope $receivedEnvelope,
        OperationMetadata $metadata,
    ): OperationResult;
}
