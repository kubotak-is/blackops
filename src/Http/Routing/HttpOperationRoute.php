<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

final readonly class HttpOperationRoute
{
    /**
     * @param class-string<OperationValue> $value
     * @param class-string<Outcome>|null $outcome
     */
    public function __construct(
        public string $method,
        public string $path,
        public Operation $operation,
        public string $value,
        public ?string $outcome = null,
        public ?bool $ephemeral = null,
    ) {}

    public function key(): string
    {
        return strtoupper($this->method) . ' ' . $this->path;
    }
}
