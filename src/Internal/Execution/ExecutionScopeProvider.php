<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use Closure;
use Throwable;

final class ExecutionScopeProvider
{
    public function __construct(
        private readonly ?TelemetryTracer $telemetry = null,
        private readonly int $spanKind = TelemetryTracer::KIND_INTERNAL,
    ) {}

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
        $span = $this->telemetry?->operation($envelope, $operationTypeId, $this->spanKind);

        try {
            return $callback();
        } catch (Throwable $failure) {
            $span?->fail($failure);
            throw $failure;
        } finally {
            array_pop($this->stack);
            array_pop($this->operationTypes);
            $span?->end();
        }
    }
}
