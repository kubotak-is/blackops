<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

final readonly class OperationConsoleCommandMetadata
{
    /**
     * @param class-string<Operation> $definition
     * @param class-string<OperationValue> $value
     * @param class-string<Outcome> $outcome
     * @param class-string<ExecutionStrategy> $strategy
     * @param list<OperationConsoleOptionMetadata> $options
     */
    public function __construct(
        public string $typeId,
        public string $definition,
        public string $value,
        public string $outcome,
        public string $strategy,
        public string $name,
        public string $description,
        public array $options,
    ) {}
}
