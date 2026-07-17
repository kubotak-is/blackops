<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final readonly class AfterCommitMethodInterceptor implements MethodInterceptor
{
    public function __construct(
        private TransactionRuntimeAccessor $transactions,
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $method = $invocation->getMethod();
        $this->transactions->afterCommit(
            $method->getDeclaringClass()->getName(),
            $method->getName(),
            static function () use ($invocation): void {
                $invocation->proceed();
            },
        );

        return null;
    }
}
