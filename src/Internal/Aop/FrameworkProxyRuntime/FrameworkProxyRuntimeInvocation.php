<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyRuntime;

use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation;
use BlackOps\Internal\Transaction\FrameworkProxyAfterCommitBinding;
use BlackOps\Internal\Transaction\FrameworkProxyTransactionBinding;
use Closure;

final readonly class FrameworkProxyRuntimeInvocation implements FrameworkProxyInvocation
{
    public function __construct(
        private FrameworkProxyTransactionBinding $transactions,
        private FrameworkProxyAfterCommitBinding $afterCommit,
    ) {}

    /** @param array<int|string,mixed> $arguments */
    public function transactional(
        object $proxy,
        string $method,
        array $arguments,
        Closure $proceed,
        ?string $connection,
    ): mixed {
        return $this->transactions->invoke($proxy, $method, $arguments, $proceed, $connection);
    }

    /** @param array<int|string,mixed> $arguments */
    public function afterCommit(object $proxy, string $method, array $arguments, Closure $proceed): void
    {
        $this->afterCommit->invoke($proxy, $method, $arguments, $proceed);
    }
}
