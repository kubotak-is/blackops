<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Attribute;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;

final class SensitiveAttributeTest extends TestCase
{
    public function testSensitiveAttributeIsPublicFinalReadonlyPropertyAttribute(): void
    {
        $reflection = new ReflectionClass(Sensitive::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertCount(1, $reflection->getAttributes(\Attribute::class));
    }

    public function testSensitiveModeIsPublicEnum(): void
    {
        $reflection = new ReflectionEnum(SensitiveMode::class);

        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['Omit', 'Mask', 'Hash'],
            array_map(static fn($case): string => $case->getName(), $reflection->getCases()),
        );
    }

    public function testSensitiveDefaultsToOmitMode(): void
    {
        self::assertSame(SensitiveMode::Omit, new Sensitive()->mode);
    }
}
