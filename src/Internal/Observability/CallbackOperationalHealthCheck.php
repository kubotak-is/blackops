<?php

declare(strict_types=1);

namespace BlackOps\Internal\Observability;

use BlackOps\Observability\OperationalHealthCheckProvider;
use Closure;

final readonly class CallbackOperationalHealthCheck implements OperationalHealthCheckProvider
{
    /** @param Closure(): bool $callback */
    public function __construct(
        private string $name,
        private Closure $callback,
    ) {}

    public function code(): string
    {
        return $this->name;
    }

    public function check(): bool
    {
        return ($this->callback)();
    }
}
