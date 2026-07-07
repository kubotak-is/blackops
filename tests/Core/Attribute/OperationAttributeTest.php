<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Attribute;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Attribute\Returns;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationAttributeTest extends TestCase
{
    public function testAttributesArePublicFinalReadonlyClassAttributes(): void
    {
        foreach ([
            OperationType::class,
            Accepts::class,
            HandledBy::class,
            Returns::class,
            ExecuteWith::class,
        ] as $type) {
            $reflection = new ReflectionClass($type);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
            self::assertCount(1, $reflection->getAttributes(\Attribute::class));
        }
    }

    public static function invalidTypeIds(): array
    {
        return [[''], ['Welcome.Show'], ['welcome show'], ['.welcome'], ['welcome.'], ['welcome..show']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTypeIds')]
    public function testInvalidOperationTypeIsRejected(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationType($id);
    }
}
