<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\OperationResult;

interface DeferredClaimRuntime
{
    public function run(OperationClaim $claim): OperationResult;
}
