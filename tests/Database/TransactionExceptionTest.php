<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Database\Exception\TransactionException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class TransactionExceptionTest extends TestCase
{
    public function testTransactionExceptionIsAPublicRuntimeException(): void
    {
        $reflection = new ReflectionClass(TransactionException::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(RuntimeException::class));
        self::assertNotEmpty($reflection->getAttributes(PublicApi::class));
    }
}
