<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Database\Exception\TransactionException;
use Closure;

final class TransactionRuntimeAccessor
{
    private ?TransactionRuntime $runtime = null;

    public function set(TransactionRuntime $runtime): void
    {
        $this->runtime = $runtime;
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    public function transactional(string $connectionName, Closure $callback): mixed
    {
        if ($this->runtime === null) {
            throw new TransactionException('Transaction runtime is not initialized.');
        }

        return $this->runtime->transactional($connectionName, $callback);
    }

    /** @param Closure(): void $callback */
    public function afterCommit(string $serviceClass, string $method, Closure $callback): void
    {
        if ($this->runtime === null) {
            throw new TransactionException('Transaction runtime is not initialized.');
        }

        $this->runtime->afterCommit($serviceClass, $method, $callback);
    }
}
