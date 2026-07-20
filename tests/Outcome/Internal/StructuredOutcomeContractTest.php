<?php

declare(strict_types=1);

namespace BlackOps\Tests\Outcome\Internal;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
use BlackOps\Outcome\Internal\StructuredOutcomeCompiler;
use BlackOps\Outcome\Internal\StructuredOutcomeNormalizer;
use BlackOps\Outcome\Internal\StructuredOutcomeValueCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class StructuredOutcomeContractTest extends TestCase
{
    public function testCompilesAndNormalizesSupportedRecursiveShapeDeterministically(): void
    {
        $compiler = new StructuredOutcomeCompiler();
        $shape = $compiler->compile(StructuredOutcomeFixture::class);

        self::assertSame(
            ['active', 'author', 'items', 'optionalAuthor', 'ratio'],
            array_column($shape->fields, 'name'),
        );
        self::assertSame(['boolean', 'dto', 'list', 'dto', 'float'], array_column($shape->fields, 'kind'));

        $author = new StructuredAuthorFixture('author-1', 'Alice');
        $outcome = new StructuredOutcomeFixture(
            true,
            $author,
            [
                new StructuredItemFixture('item-1', $author, 1),
                new StructuredItemFixture('item-2', null, 2),
            ],
            null,
            1.0,
        );

        self::assertSame(
            [
                'active' => true,
                'author' => ['id' => 'author-1', 'name' => 'Alice'],
                'items' => [
                    ['author' => ['id' => 'author-1', 'name' => 'Alice'], 'id' => 'item-1', 'sequence' => 1],
                    ['author' => null, 'id' => 'item-2', 'sequence' => 2],
                ],
                'optionalAuthor' => null,
                'ratio' => 1.0,
            ],
            new StructuredOutcomeNormalizer()->normalize($outcome),
        );
    }

    public function testValueCodecRoundTripsNestedListsNullableDtoAndFloatOnePointZero(): void
    {
        $author = new StructuredAuthorFixture('author-1', 'Alice');
        $outcome = new StructuredOutcomeFixture(
            true,
            $author,
            [
                new StructuredItemFixture('item-1', $author, 1),
                new StructuredItemFixture('item-2', null, 2),
            ],
            null,
            1.0,
        );
        $codec = new StructuredOutcomeValueCodec();

        $encoded = $codec->encode($outcome);
        $json = json_encode($encoded, JSON_THROW_ON_ERROR);
        $decodedPayload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decodedPayload);
        $decoded = $codec->decode($decodedPayload);

        self::assertEquals($outcome, $decoded);
        self::assertSame(1.0, $decoded->ratio);
    }

    /** @return iterable<string, array{class-string<Outcome>, string}> */
    public static function invalidContracts(): iterable
    {
        yield 'array without ListOf' => [ArrayWithoutListOfOutcomeFixture::class, 'one ListOf'];
        yield 'nullable list' => [NullableListOutcomeFixture::class, 'non-nullable array'];
        yield 'unknown element' => [UnknownListElementOutcomeFixture::class, 'concrete OutcomeData'];
        yield 'object outside OutcomeData' => [UnsupportedObjectOutcomeFixture::class, 'concrete OutcomeData'];
        yield 'union field' => [UnionOutcomeFixture::class, 'unsupported type'];
        yield 'non-final dto' => [NonFinalDtoOutcomeFixture::class, 'final readonly'];
        yield 'non-readonly dto' => [NonReadonlyDtoOutcomeFixture::class, 'final readonly'];
        yield 'non-promoted dto field' => [NonPromotedDtoOutcomeFixture::class, 'public promoted'];
        yield 'extra dto constructor parameter' => [ExtraParameterDtoOutcomeFixture::class, 'only promoted fields'];
        yield 'private root field' => [PrivateRootOutcomeFixture::class, 'public instance property'];
        yield 'static root field' => [StaticRootOutcomeFixture::class, 'public instance property'];
        yield 'cycle' => [CycleOutcomeFixture::class, 'cycle detected'];
    }

    #[DataProvider('invalidContracts')]
    public function testRejectsUnsupportedContracts(string $class, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new StructuredOutcomeCompiler()->compile($class);
    }

    public function testRejectsMapWrongElementAndUninitializedRootValueAtRuntime(): void
    {
        $normalizer = new StructuredOutcomeNormalizer();

        foreach ([
            new ListOnlyOutcomeFixture(['key' => new StructuredAuthorFixture('a', 'Alice')]),
            new ListOnlyOutcomeFixture([new \stdClass()]),
            new UninitializedRootOutcomeFixture('not-assigned'),
        ] as $outcome) {
            try {
                $normalizer->normalize($outcome);
                self::fail('Expected invalid runtime structured outcome.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString(__FILE__, $exception->getMessage());
            }
        }
    }

    public function testAcceptsEmptyTypedList(): void
    {
        self::assertSame(['items' => []], new StructuredOutcomeNormalizer()->normalize(new ListOnlyOutcomeFixture([])));
    }

    public function testNormalizesZeroFieldDtoAsJsonObjectsWithoutChangingZeroFieldRoot(): void
    {
        $empty = new ZeroFieldOutcomeDataFixture();
        $normalizer = new StructuredOutcomeNormalizer();
        $normalized = $normalizer->normalize(
            new ZeroFieldNestedOutcomeFixture([$empty, new ZeroFieldOutcomeDataFixture()], $empty, $empty),
        );

        self::assertInstanceOf(stdClass::class, $normalized['nested']);
        self::assertInstanceOf(stdClass::class, $normalized['optional']);
        self::assertContainsOnlyInstancesOf(stdClass::class, $normalized['items']);
        self::assertSame('{"items":[{},{}],"nested":{},"optional":{}}', json_encode($normalized, JSON_THROW_ON_ERROR));
        self::assertSame([], $normalizer->normalize(new ZeroFieldRootOutcomeFixture()));
    }
}

final readonly class ZeroFieldOutcomeDataFixture implements OutcomeData {}

final readonly class ZeroFieldRootOutcomeFixture implements Outcome {}

final readonly class ZeroFieldNestedOutcomeFixture implements Outcome
{
    /** @param list<ZeroFieldOutcomeDataFixture> $items */
    public function __construct(
        #[ListOf(ZeroFieldOutcomeDataFixture::class)]
        public array $items,
        public ZeroFieldOutcomeDataFixture $nested,
        public ?ZeroFieldOutcomeDataFixture $optional,
    ) {}
}

final readonly class StructuredAuthorFixture implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

final readonly class StructuredItemFixture implements OutcomeData
{
    public function __construct(
        public string $id,
        public ?StructuredAuthorFixture $author,
        public int $sequence,
    ) {}
}

final readonly class StructuredOutcomeFixture implements Outcome
{
    /** @param list<StructuredItemFixture> $items */
    public function __construct(
        public bool $active,
        public StructuredAuthorFixture $author,
        #[ListOf(StructuredItemFixture::class)]
        public array $items,
        public ?StructuredAuthorFixture $optionalAuthor,
        public float $ratio,
    ) {}
}

final readonly class ListOnlyOutcomeFixture implements Outcome
{
    /** @param array<array-key, mixed> $items */
    public function __construct(
        #[ListOf(StructuredAuthorFixture::class)]
        public array $items,
    ) {}
}

final readonly class ArrayWithoutListOfOutcomeFixture implements Outcome
{
    public function __construct(
        public array $items,
    ) {}
}

final readonly class NullableListOutcomeFixture implements Outcome
{
    public function __construct(
        #[ListOf(StructuredAuthorFixture::class)]
        public ?array $items,
    ) {}
}

final readonly class UnknownListElementOutcomeFixture implements Outcome
{
    public function __construct(
        #[ListOf('Unknown\\OutcomeData')]
        public array $items,
    ) {}
}

final readonly class UnsupportedObjectOutcomeFixture implements Outcome
{
    public function __construct(
        public \stdClass $value,
    ) {}
}

final readonly class UnionOutcomeFixture implements Outcome
{
    public function __construct(
        public string|int $value,
    ) {}
}

class NonFinalOutcomeDataFixture implements OutcomeData
{
    public function __construct(
        public readonly string $value,
    ) {}
}

final readonly class NonFinalDtoOutcomeFixture implements Outcome
{
    public function __construct(
        public NonFinalOutcomeDataFixture $value,
    ) {}
}

final class NonReadonlyOutcomeDataFixture implements OutcomeData
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class NonReadonlyDtoOutcomeFixture implements Outcome
{
    public function __construct(
        public NonReadonlyOutcomeDataFixture $value,
    ) {}
}

final readonly class NonPromotedOutcomeDataFixture implements OutcomeData
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}

final readonly class NonPromotedDtoOutcomeFixture implements Outcome
{
    public function __construct(
        public NonPromotedOutcomeDataFixture $value,
    ) {}
}

final readonly class ExtraParameterOutcomeDataFixture implements OutcomeData
{
    public function __construct(
        public string $value,
        string $extra,
    ) {}
}

final readonly class ExtraParameterDtoOutcomeFixture implements Outcome
{
    public function __construct(
        public ExtraParameterOutcomeDataFixture $value,
    ) {}
}

final readonly class PrivateRootOutcomeFixture implements Outcome
{
    public function __construct(
        private string $value,
    ) {}
}

final class StaticRootOutcomeFixture implements Outcome
{
    public static string $value = 'static';
}

final readonly class CycleOutcomeFixture implements Outcome
{
    public function __construct(
        public CycleOutcomeDataFixture $value,
    ) {}
}

final readonly class CycleOutcomeDataFixture implements OutcomeData
{
    public function __construct(
        public CycleOutcomeDataFixture $next,
    ) {}
}

final class UninitializedRootOutcomeFixture implements Outcome
{
    public string $value;

    public function __construct(string $value)
    {
        unset($value);
    }
}
