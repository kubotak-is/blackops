<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;

final readonly class HttpOperationRoute
{
    /**
     * @param class-string<OperationValue> $value
     */
    public function __construct(
        public string $method,
        public string $path,
        public Operation $operation,
        public string $value,
    ) {}

    public function key(): string
    {
        return strtoupper($this->method) . ' ' . $this->path;
    }
}
