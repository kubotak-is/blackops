<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\OperationHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationHandlerTest extends TestCase
{
    public function testHandlerIsPublicSingleMethodInterfaceWithGenerics(): void
    {
        $reflection = new ReflectionClass(OperationHandler::class);
        $methods = $reflection->getMethods();
        $doc = $reflection->getDocComment();
        $methodDoc = $methods[0]->getDocComment();

        self::assertTrue($reflection->isInterface());
        self::assertCount(1, $methods);
        self::assertSame('handle', $methods[0]->getName());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertIsString($doc);
        self::assertStringContainsString('@template TValue of OperationValue', $doc);
        self::assertStringContainsString('@template TOutcome of Outcome', $doc);
        self::assertIsString($methodDoc);
        self::assertStringContainsString('OperationEnvelope<TValue>', $methodDoc);
        self::assertStringContainsString('OperationResult<TOutcome>', $methodDoc);
    }
}
