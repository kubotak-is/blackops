<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendOperationContract
{
    public function __construct(
        public string $typeId,
        public string $definition,
        public string $exportName,
        public string $module,
        public string $method,
        public string $path,
        public string $strategy,
        public FrontendValueContract $value,
        public FrontendOutcomeContract $outcome,
    ) {}
}
