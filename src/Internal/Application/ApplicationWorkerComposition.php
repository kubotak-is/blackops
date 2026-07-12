<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Execution\DeferredWorkerLoop;
use BlackOps\Internal\Execution\PcntlSignalHeartbeat;
use Doctrine\DBAL\Connection;

final readonly class ApplicationWorkerComposition
{
    public function __construct(
        public DeferredWorkerLoop $loop,
        public Connection $mainConnection,
        public Connection $heartbeatConnection,
        public PcntlSignalHeartbeat $signals,
    ) {}
}
