<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;

/**
 * Immutable combination of an operation definition, its typed value, execution context, and strategy.
 *
 * @template-covariant TValue of OperationValue
 */
#[PublicApi]
final readonly class OperationEnvelope
{
    /**
     * @param TValue $value
     */
    public function __construct(
        private Operation $definition,
        private OperationValue $value,
        private ExecutionContext $context,
        private ExecutionStrategy $strategy,
    ) {}

    public function definition(): Operation
    {
        return $this->definition;
    }

    /**
     * @return TValue
     */
    public function value(): OperationValue
    {
        return $this->value;
    }

    public function context(): ExecutionContext
    {
        return $this->context;
    }

    public function strategy(): ExecutionStrategy
    {
        return $this->strategy;
    }

    public function id(): OperationId
    {
        return $this->context->operationId();
    }

    public function receivedAt(): DateTimeImmutable
    {
        return $this->context->receivedAt();
    }
}
