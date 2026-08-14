<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

final class FrameworkProxyInvocationRegistry
{
    /** @var \WeakMap<object,FrameworkProxyInvocation>|null */
    private static ?\WeakMap $invocations = null;

    public static function initialize(object $proxy, FrameworkProxyInvocation $invocation): void
    {
        self::$invocations ??= new \WeakMap();
        if (isset(self::$invocations[$proxy])) {
            throw new \LogicException('Framework proxy invocation is already initialized.');
        }
        self::$invocations[$proxy] = $invocation;
    }

    public static function get(object $proxy): ?FrameworkProxyInvocation
    {
        return self::$invocations?->offsetExists($proxy) ? self::$invocations[$proxy] : null;
    }
}
