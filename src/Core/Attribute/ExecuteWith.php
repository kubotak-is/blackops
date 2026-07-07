<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\Execution\ExecutionStrategy;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ExecuteWith
{
    /** @param class-string<ExecutionStrategy> $strategy */
    public function __construct(
        public string $strategy,
    ) {}
}
