<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Validation;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Count;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ValidationAttributeTest extends TestCase
{
    /** @return iterable<array{class-string}> */
    public static function attributes(): iterable
    {
        yield [NotBlank::class];
        yield [Length::class];
        yield [Range::class];
        yield [Email::class];
        yield [Regex::class];
        yield [Count::class];
        yield [Choice::class];
    }

    /** @param class-string $attribute */
    #[DataProvider('attributes')]
    public function testRuleIsFinalReadonlyPublicPropertyAttribute(string $attribute): void
    {
        $reflection = new ReflectionClass($attribute);
        $metadata = $reflection->getAttributes(Attribute::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertCount(1, $metadata);
        self::assertSame(Attribute::TARGET_PROPERTY, $metadata[0]->newInstance()->flags);
    }

    /** @return iterable<string, array{Closure(): object}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'length without bound' => [static fn(): Length => new Length()];
        yield 'length negative minimum' => [static fn(): Length => new Length(min: -1)];
        yield 'length negative maximum' => [static fn(): Length => new Length(max: -1)];
        yield 'length inverted bounds' => [static fn(): Length => new Length(min: 2, max: 1)];
        yield 'range without bound' => [static fn(): Range => new Range()];
        yield 'range infinite minimum' => [static fn(): Range => new Range(min: INF)];
        yield 'range not-a-number maximum' => [static fn(): Range => new Range(max: NAN)];
        yield 'range inverted bounds' => [static fn(): Range => new Range(min: 2, max: 1)];
        yield 'count without bound' => [static fn(): Count => new Count()];
        yield 'count negative minimum' => [static fn(): Count => new Count(min: -1)];
        yield 'count negative maximum' => [static fn(): Count => new Count(max: -1)];
        yield 'count inverted bounds' => [static fn(): Count => new Count(min: 2, max: 1)];
        yield 'empty regex' => [static fn(): Regex => new Regex('')];
        yield 'invalid regex' => [static fn(): Regex => new Regex('/[/')];
        yield 'empty choices' => [static fn(): Choice => new Choice([])];
        yield 'associative choices' => [static fn(): Choice => new Choice(['status' => 'draft'])];
        yield 'non-scalar choice' => [static fn(): Choice => new Choice([[]])];
        yield 'infinite choice' => [static fn(): Choice => new Choice([INF])];
        yield 'duplicate choice' => [static fn(): Choice => new Choice(['draft', 'draft'])];
    }

    /** @param Closure(): object $create */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidRuleConfiguration(Closure $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    public function testPreservesValidRuleConfiguration(): void
    {
        $length = new Length(min: 1, max: 4);
        $range = new Range(min: -1.5, max: 2);
        $count = new Count(min: 0, max: 3);
        $regex = new Regex('/^[a-z]+$/');
        $choice = new Choice(['draft', 1, true]);

        self::assertSame([1, 4], [$length->min, $length->max]);
        self::assertSame([-1.5, 2], [$range->min, $range->max]);
        self::assertSame([0, 3], [$count->min, $count->max]);
        self::assertSame('/^[a-z]+$/', $regex->pattern);
        self::assertSame(['draft', 1, true], $choice->choices);
    }
}
