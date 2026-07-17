<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final readonly class TransactionalMethodInterceptor implements MethodInterceptor
{
    public function __construct(
        private TransactionRuntimeAccessor $transactions,
        private string $connectionName,
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $this->transactions->transactional($this->connectionName, $invocation->proceed(...));
    }
}
