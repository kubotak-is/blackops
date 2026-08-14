<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyAfterCommitInvocation;
use BlackOps\Internal\Execution\FrameworkProxyOperationOwnershipGuard;
use Closure;

final readonly class FrameworkProxyAfterCommitBinding
{
    public function __construct(
        private TransactionRuntimeAccessor $transactions,
        private FrameworkProxyDefinitionBinding $binding,
        private FrameworkProxyOperationOwnershipGuard $ownership = new FrameworkProxyOperationOwnershipGuard(),
    ) {}

    /** @param array<int|string,mixed> $arguments */
    public function invoke(object $proxy, string $method, array $arguments, Closure $proceed): void
    {
        $this->ownership->assertAfterCommitInvocation($this->binding, $method);
        $receiver = $proxy;
        $queued = new FrameworkProxyAfterCommitInvocation($receiver, $method, $arguments, $proceed);
        $this->transactions->afterCommit($this->binding->sourceClass, $method, $queued->invoke(...));
    }
}
