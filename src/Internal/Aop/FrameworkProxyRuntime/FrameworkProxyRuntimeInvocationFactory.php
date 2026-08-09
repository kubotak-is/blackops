<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyRuntime;

use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation;
use BlackOps\Internal\Execution\FrameworkProxyOperationOwnershipGuard;
use BlackOps\Internal\Transaction\FrameworkProxyAfterCommitBinding;
use BlackOps\Internal\Transaction\FrameworkProxyTransactionBinding;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;

final readonly class FrameworkProxyRuntimeInvocationFactory
{
    public function __construct(
        private TransactionRuntimeAccessor $transactions,
        private FrameworkProxyOperationOwnershipGuard $ownership = new FrameworkProxyOperationOwnershipGuard(),
    ) {}

    public function create(FrameworkProxyDefinitionBinding $binding): FrameworkProxyInvocation
    {
        $this->ownership->assertFrameworkBinding($binding);

        return new FrameworkProxyRuntimeInvocation(
            new FrameworkProxyTransactionBinding($this->transactions, $binding, $this->ownership),
            new FrameworkProxyAfterCommitBinding($this->transactions, $binding, $this->ownership),
        );
    }
}
