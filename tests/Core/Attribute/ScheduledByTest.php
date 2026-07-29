<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Attribute;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Attribute\ScheduledBy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ScheduledByTest extends TestCase
{
    public function testPublicNonRepeatableClassTargetShape(): void
    {
        $reflection = new ReflectionClass(ScheduledBy::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
        self::assertFalse(($attribute->flags & \Attribute::IS_REPEATABLE) !== 0);
    }

    public function testDefaultsTimezoneToUtc(): void
    {
        $attribute = new ScheduledBy('reports.daily', '0 0 * * *');

        self::assertSame('reports.daily', $attribute->name);
        self::assertSame('0 0 * * *', $attribute->cron);
        self::assertSame('UTC', $attribute->timezone);
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $name, string $cron, string $timezone): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduledBy($name, $cron, $timezone);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidValues(): iterable
    {
        yield 'name' => ['Reports.Daily', '0 0 * * *', 'UTC'];
        yield 'field count' => ['reports.daily', '0 0 * *', 'UTC'];
        yield 'nickname' => ['reports.daily', '@daily', 'UTC'];
        yield 'single value step' => ['reports.daily', '5/2 * * * *', 'UTC'];
        yield 'timezone' => ['reports.daily', '0 0 * * *', 'Local/Host'];
    }
}
