<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\OperationClaim;
use Closure;

interface ClaimExecutionGuard
{
    /**
     * @template TResult
     *
     * @param Closure(): TResult $operation
     *
     * @return TResult
     */
    public function run(OperationClaim $claim, Closure $operation): mixed;
}
