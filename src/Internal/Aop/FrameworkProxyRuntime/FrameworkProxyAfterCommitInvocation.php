<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyRuntime;

use Closure;

final readonly class FrameworkProxyAfterCommitInvocation
{
    /** @param array<int|string,mixed> $arguments */
    public function __construct(
        public object $receiver,
        public string $method,
        public array $arguments,
        private Closure $proceed,
    ) {}

    public function invoke(): void
    {
        ($this->proceed)();
    }
}
