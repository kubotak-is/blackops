<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final readonly class FoundationMethodInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}
