<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop;

use BlackOps\Internal\Aop\FoundationMethodInterceptor;
use PHPUnit\Framework\TestCase;
use Ray\Aop\MethodInvocation;

final class FoundationMethodInterceptorTest extends TestCase
{
    public function testProceedsExactlyOnceAndReturnsOriginalValue(): void
    {
        $calls = 0;
        $invocation = $this->createMock(MethodInvocation::class);
        $invocation
            ->expects(self::once())
            ->method('proceed')
            ->willReturnCallback(static function () use (&$calls): string {
                $calls++;

                return 'result';
            });

        self::assertSame('result', new FoundationMethodInterceptor()->invoke($invocation));
        self::assertSame(1, $calls);
    }

    public function testPropagatesOriginalThrowable(): void
    {
        $expected = new \RuntimeException('expected');
        $invocation = $this->createStub(MethodInvocation::class);
        $invocation->method('proceed')->willThrowException($expected);

        try {
            new FoundationMethodInterceptor()->invoke($invocation);
            self::fail('Expected original throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($expected, $throwable);
        }
    }
}
