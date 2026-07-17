<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TransactionAttributeTest extends TestCase
{
    public function testTransactionalContractAndConnectionNormalization(): void
    {
        $reflection = new ReflectionClass(Transactional::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD, $attribute->flags);
        self::assertNotEmpty($reflection->getAttributes(PublicApi::class));
        self::assertNull(new Transactional()->connection);
        self::assertSame('analytics', new Transactional(' analytics ')->connection);
    }

    public function testTransactionalRejectsEmptyConnectionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        new Transactional('  ');
    }

    public function testAfterCommitContract(): void
    {
        $reflection = new ReflectionClass(AfterCommit::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_METHOD, $attribute->flags);
        self::assertNotEmpty($reflection->getAttributes(PublicApi::class));
    }
}
