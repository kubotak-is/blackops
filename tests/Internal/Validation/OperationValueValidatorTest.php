<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Validation;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Count;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Validation\OperationValueValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationValueValidatorTest extends TestCase
{
    /** @return iterable<string, array{OperationValue, list<Violation>}> */
    public static function ruleValues(): iterable
    {
        yield 'not blank accepts content' => [new NotBlankValue('value'), []];
        yield 'not blank rejects empty' => [
            new NotBlankValue(''),
            [new Violation('value', 'not_blank', 'validation.not_blank')],
        ];
        yield 'not blank rejects whitespace' => [
            new NotBlankValue(" \t\n"),
            [new Violation('value', 'not_blank', 'validation.not_blank')],
        ];
        yield 'not blank rejects unicode whitespace' => [
            new NotBlankValue("\u{3000}"),
            [new Violation('value', 'not_blank', 'validation.not_blank')],
        ];
        yield 'length accepts minimum' => [new LengthValue('あい'), []];
        yield 'length accepts maximum' => [new LengthValue('abcd'), []];
        yield 'length rejects below minimum' => [
            new LengthValue('a'),
            [new Violation('value', 'length', 'validation.length')],
        ];
        yield 'length rejects above maximum' => [
            new LengthValue('abcde'),
            [new Violation('value', 'length', 'validation.length')],
        ];
        yield 'zero maximum length accepts empty' => [new ZeroLengthValue(''), []];
        yield 'zero maximum length rejects content' => [
            new ZeroLengthValue('a'),
            [new Violation('value', 'length', 'validation.length')],
        ];
        yield 'range accepts integer minimum' => [new RangeValue(1), []];
        yield 'range accepts float within bounds' => [new RangeValue(5.5), []];
        yield 'range accepts integer maximum' => [new RangeValue(10), []];
        yield 'range rejects below minimum' => [
            new RangeValue(0),
            [new Violation('value', 'range', 'validation.range')],
        ];
        yield 'range rejects above maximum' => [
            new RangeValue(10.1),
            [new Violation('value', 'range', 'validation.range')],
        ];
        yield 'email accepts valid address' => [new EmailValue('reader@example.com'), []];
        yield 'email rejects invalid address' => [
            new EmailValue('not-an-email'),
            [new Violation('value', 'email', 'validation.email')],
        ];
        yield 'regex accepts match' => [new RegexValue('AB12'), []];
        yield 'regex rejects mismatch' => [
            new RegexValue('ab12'),
            [new Violation('value', 'regex', 'validation.regex')],
        ];
        yield 'count accepts minimum' => [new CountValue(['first']), []];
        yield 'count accepts maximum' => [new CountValue(['first', 'second']), []];
        yield 'count rejects below minimum' => [
            new CountValue([]),
            [new Violation('value', 'count', 'validation.count')],
        ];
        yield 'count rejects above maximum' => [
            new CountValue(['first', 'second', 'third']),
            [new Violation('value', 'count', 'validation.count')],
        ];
        yield 'zero maximum count accepts empty' => [new ZeroCountValue([]), []];
        yield 'zero maximum count rejects item' => [
            new ZeroCountValue(['first']),
            [new Violation('value', 'count', 'validation.count')],
        ];
        yield 'choice accepts string strictly' => [new ChoiceValue('draft'), []];
        yield 'choice accepts integer strictly' => [new ChoiceValue(1), []];
        yield 'choice rejects different scalar type' => [
            new ChoiceValue('1'),
            [new Violation('value', 'choice', 'validation.choice')],
        ];
        yield 'choice rejects unknown value' => [
            new ChoiceValue('archived'),
            [new Violation('value', 'choice', 'validation.choice')],
        ];
    }

    /** @param list<Violation> $expected */
    #[DataProvider('ruleValues')]
    public function testValidatesRuleSuccessFailureAndBoundaries(OperationValue $value, array $expected): void
    {
        self::assertEquals($expected, new OperationValueValidator()->validate($value));
    }

    public function testAggregatesEveryViolationInDeterministicFieldAndRuleOrder(): void
    {
        $violations = new OperationValueValidator()->validate(new AggregateValue('wrong', '', []));

        self::assertEquals(
            [
                new Violation('alpha', 'not_blank', 'validation.not_blank'),
                new Violation('alpha', 'choice', 'validation.choice'),
                new Violation('items', 'count', 'validation.count'),
                new Violation('zeta', 'email', 'validation.email'),
                new Violation('zeta', 'regex', 'validation.regex'),
            ],
            $violations,
        );
    }

    /** @return iterable<string, array{OperationValue, Violation}> */
    public static function wrongTargetTypes(): iterable
    {
        yield 'not blank requires string' => [
            new WrongNotBlankTarget(1),
            new Violation('value', 'not_blank', 'validation.not_blank'),
        ];
        yield 'length requires string' => [
            new WrongLengthTarget(1),
            new Violation('value', 'length', 'validation.length'),
        ];
        yield 'range requires number' => [
            new WrongRangeTarget('1'),
            new Violation('value', 'range', 'validation.range'),
        ];
        yield 'email requires string' => [
            new WrongEmailTarget(1),
            new Violation('value', 'email', 'validation.email'),
        ];
        yield 'regex requires string' => [
            new WrongRegexTarget(1),
            new Violation('value', 'regex', 'validation.regex'),
        ];
        yield 'count requires array' => [
            new WrongCountTarget('one'),
            new Violation('value', 'count', 'validation.count'),
        ];
    }

    #[DataProvider('wrongTargetTypes')]
    public function testWrongTargetTypeBecomesSafeViolation(OperationValue $value, Violation $expected): void
    {
        self::assertEquals([$expected], new OperationValueValidator()->validate($value));
    }

    public function testViolationNeverRetainsOrRendersSensitiveRawValue(): void
    {
        $secret = 'private-token-47d920';
        $violations = new OperationValueValidator()->validate(new SensitiveValue($secret));
        $rendered =
            serialize($violations) . var_export($violations, true) . json_encode($violations, JSON_THROW_ON_ERROR);

        self::assertEquals([new Violation('accessToken', 'regex', 'validation.regex')], $violations);
        self::assertStringNotContainsString($secret, $rendered);
    }

    public function testIgnoresPropertiesThatAreNotConstructorPromoted(): void
    {
        self::assertSame([], new OperationValueValidator()->validate(new NonPromotedValue('')));
    }
}

final readonly class NotBlankValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $value,
    ) {}
}

final readonly class LengthValue implements OperationValue
{
    public function __construct(
        #[Length(min: 2, max: 4)]
        public string $value,
    ) {}
}

final readonly class ZeroLengthValue implements OperationValue
{
    public function __construct(
        #[Length(max: 0)]
        public string $value,
    ) {}
}

final readonly class RangeValue implements OperationValue
{
    public function __construct(
        #[Range(min: 1, max: 10)]
        public int|float $value,
    ) {}
}

final readonly class EmailValue implements OperationValue
{
    public function __construct(
        #[Email]
        public string $value,
    ) {}
}

final readonly class RegexValue implements OperationValue
{
    public function __construct(
        #[Regex('/^[A-Z]{2}\d{2}$/')]
        public string $value,
    ) {}
}

final readonly class CountValue implements OperationValue
{
    /** @param list<string> $value */
    public function __construct(
        #[Count(min: 1, max: 2)]
        public array $value,
    ) {}
}

final readonly class ZeroCountValue implements OperationValue
{
    /** @param list<string> $value */
    public function __construct(
        #[Count(max: 0)]
        public array $value,
    ) {}
}

final readonly class ChoiceValue implements OperationValue
{
    public function __construct(
        #[Choice(['draft', 'published', 1])]
        public int|string $value,
    ) {}
}

final readonly class AggregateValue implements OperationValue
{
    /** @param list<string> $items */
    public function __construct(
        #[Regex('/^Z$/')]
        #[Email]
        public string $zeta,
        #[Choice(['ready'])]
        #[NotBlank]
        public string $alpha,
        #[Count(min: 1)]
        public array $items,
    ) {}
}

final readonly class SensitiveValue implements OperationValue
{
    public function __construct(
        #[Sensitive]
        #[Regex('/^allowed$/')]
        public string $accessToken,
    ) {}
}

final class NonPromotedValue implements OperationValue
{
    #[NotBlank]
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}

final readonly class WrongNotBlankTarget implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public int|string $value,
    ) {}
}

final readonly class WrongLengthTarget implements OperationValue
{
    public function __construct(
        #[Length(min: 1)]
        public int|string $value,
    ) {}
}

final readonly class WrongRangeTarget implements OperationValue
{
    public function __construct(
        #[Range(min: 1)]
        public int|float|string $value,
    ) {}
}

final readonly class WrongEmailTarget implements OperationValue
{
    public function __construct(
        #[Email]
        public int|string $value,
    ) {}
}

final readonly class WrongRegexTarget implements OperationValue
{
    public function __construct(
        #[Regex('/^value$/')]
        public int|string $value,
    ) {}
}

final readonly class WrongCountTarget implements OperationValue
{
    public function __construct(
        #[Count(min: 1)]
        public array|string $value,
    ) {}
}
