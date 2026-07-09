<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\OperationEnvelope;
use Closure;

final class ExecutionScopeProvider
{
    /**
     * @var list<OperationEnvelope>
     */
    private array $stack = [];

    public function current(): ?OperationEnvelope
    {
        $index = array_key_last($this->stack);

        if ($index === null) {
            return null;
        }

        return $this->stack[$index];
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $callback
     *
     * @return TResult
     */
    public function run(OperationEnvelope $envelope, Closure $callback): mixed
    {
        $this->stack[] = $envelope;

        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }
}
