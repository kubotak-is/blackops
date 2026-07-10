<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Core\Supervision\SupervisionPolicy;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;

final readonly class DeferredWorkerRuntimeServices
{
    public function __construct(
        public OperationRegistry $registry,
        public OperationCodec $codec,
        public ExecutionContextFactory $contexts,
        public HandlerResolver $handlers,
        public SupervisionPolicy $supervision = new ExponentialBackoffSupervisionPolicy(),
    ) {}
}
