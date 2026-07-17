<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Core\ExecutionContext;
use Closure;

final readonly class AfterCommitInvocation
{
    /** @param Closure(): void $callback */
    public function __construct(
        public string $serviceClass,
        public string $method,
        public Closure $callback,
        public ?ExecutionContext $context,
    ) {}
}
