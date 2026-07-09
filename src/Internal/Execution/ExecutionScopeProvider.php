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

    /**
     * @var list<string|null>
     */
    private array $operationTypes = [];

    public function current(): ?OperationEnvelope
    {
        $index = array_key_last($this->stack);

        if ($index === null) {
            return null;
        }

        return $this->stack[$index];
    }

    public function currentOperationTypeId(): ?string
    {
        $index = array_key_last($this->operationTypes);

        if ($index === null) {
            return null;
        }

        return $this->operationTypes[$index];
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $callback
     *
     * @return TResult
     */
    public function run(OperationEnvelope $envelope, Closure $callback, ?string $operationTypeId = null): mixed
    {
        $this->stack[] = $envelope;
        $this->operationTypes[] = $operationTypeId;

        try {
            return $callback();
        } finally {
            array_pop($this->stack);
            array_pop($this->operationTypes);
        }
    }
}
