<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use Closure;

interface WorkerSignalRuntime
{
    /**
     * @template TResult
     *
     * @param Closure(): TResult $loop
     *
     * @return TResult
     */
    public function runLoop(Closure $loop): mixed;

    public function stopRequested(): bool;
}
