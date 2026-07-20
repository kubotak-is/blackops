<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Attribute;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\OutcomeData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class ListOfAttributeTest extends TestCase
{
    public function testIsReadonlyPublicApiAttributeTargetingProperties(): void
    {
        $reflection = new ReflectionClass(ListOf::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));

        $attributes = $reflection->getAttributes(\Attribute::class);
        self::assertCount(1, $attributes);
        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    public function testStoresElementClassWithoutEagerValidation(): void
    {
        $property = new ReflectionProperty(ListOfAttributeFixture::class, 'items');
        $attributes = $property->getAttributes(ListOf::class);

        self::assertCount(1, $attributes);
        /** @var ListOf $list */
        $list = $attributes[0]->newInstance();
        self::assertSame(ListOfElementFixture::class, $list->type);
        self::assertSame('Unknown\\DeferredClass', new ListOf('Unknown\\DeferredClass')->type);
    }
}

final readonly class ListOfElementFixture implements OutcomeData {}

final readonly class ListOfAttributeFixture
{
    /** @param list<ListOfElementFixture> $items */
    public function __construct(
        #[ListOf(ListOfElementFixture::class)]
        public array $items,
    ) {}
}
