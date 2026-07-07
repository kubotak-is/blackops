<?php

declare(strict_types=1);

namespace BlackOps\Core\Registry;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

#[PublicApi]
final readonly class OperationMetadata
{
    /**
     * @param class-string<Operation> $definition
     * @param class-string<OperationValue> $value
     * @param class-string<OperationHandler> $handler
     * @param class-string<Outcome> $outcome
     * @param class-string<ExecutionStrategy> $strategy
     */
    public function __construct(
        public string $typeId,
        public string $definition,
        public string $value,
        public string $handler,
        public string $outcome,
        public string $strategy,
    ) {}
}
