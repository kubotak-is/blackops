<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

use Closure;

/**
 * The only runtime ABI emitted by a Framework proxy. It intentionally has one
 * operation for each supported attribute and no interceptor registry.
 */
interface FrameworkProxyInvocation
{
    /** @param array<int|string,mixed> $arguments */
    public function transactional(
        object $proxy,
        string $method,
        array $arguments,
        Closure $proceed,
        ?string $connection,
    ): mixed;

    /** @param array<int|string,mixed> $arguments */
    public function afterCommit(object $proxy, string $method, array $arguments, Closure $proceed): void;
}
