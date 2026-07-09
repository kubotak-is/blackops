<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExecutionStrategyTest extends TestCase
{
    public function testExecutionStrategyIsPublicMarkerInterface(): void
    {
        $reflection = new ReflectionClass(ExecutionStrategy::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame([], $reflection->getMethods());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testInlineIsPublicFinalReadonlyStrategy(): void
    {
        $reflection = new ReflectionClass(Inline::class);
        $inline = new Inline();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertInstanceOf(ExecutionStrategy::class, $inline);
    }

    public function testDeferredIsPublicFinalReadonlyStrategy(): void
    {
        $reflection = new ReflectionClass(Deferred::class);
        $deferred = new Deferred();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertInstanceOf(ExecutionStrategy::class, $deferred);
    }
}
