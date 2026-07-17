<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final readonly class AfterCommitBindingInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}
