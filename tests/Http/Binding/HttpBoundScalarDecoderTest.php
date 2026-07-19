<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Binding;

use BlackOps\Http\Binding\HttpBoundScalarDecoder;
use BlackOps\Http\Binding\OperationValueBindingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionParameter;

final class HttpBoundScalarDecoderTest extends TestCase
{
    #[DataProvider('canonicalValues')]
    public function testCanonicalStringsDecodeToDeclaredScalarType(
        string $parameter,
        string $wireValue,
        mixed $expected,
    ): void {
        $decoded = new HttpBoundScalarDecoder()->decode($this->parameter($parameter), $wireValue);

        self::assertSame($expected, $decoded);
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function canonicalValues(): iterable
    {
        yield 'string stays empty' => ['string', '', ''];
        yield 'integer zero' => ['integer', '0', 0];
        yield 'integer negative' => ['integer', '-1', -1];
        yield 'integer positive' => ['integer', '42', 42];
        yield 'integer maximum' => ['integer', (string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'integer minimum' => ['integer', (string) PHP_INT_MIN, PHP_INT_MIN];
        yield 'float integer form' => ['float', '42', 42.0];
        yield 'float negative zero' => ['float', '-0', -0.0];
        yield 'float fraction' => ['float', '-0.5', -0.5];
        yield 'float exponent' => ['float', '1.25e+2', 125.0];
        yield 'boolean true' => ['boolean', 'true', true];
        yield 'boolean false' => ['boolean', 'false', false];
    }

    #[DataProvider('invalidValues')]
    public function testNonCanonicalOrUnsafeStringsAreRejected(string $parameter, string $wireValue): void
    {
        try {
            new HttpBoundScalarDecoder()->decode($this->parameter($parameter), $wireValue);
            self::fail('Expected scalar decode to fail.');
        } catch (OperationValueBindingException $exception) {
            self::assertSame($parameter, $exception->violations()[0]->field);
            self::assertSame('type', $exception->violations()[0]->rule);
            self::assertSame('binding.type', $exception->violations()[0]->code);

            if ($wireValue !== '') {
                self::assertStringNotContainsString($wireValue, $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidValues(): iterable
    {
        foreach (['', '+1', '01', '-0', ' 1', '1 ', '1.0', '1e2'] as $value) {
            yield 'integer ' . json_encode($value, JSON_THROW_ON_ERROR) => ['integer', $value];
        }

        yield 'integer positive overflow' => ['integer', (string) PHP_INT_MAX . '0'];
        yield 'integer negative overflow' => ['integer', (string) PHP_INT_MIN . '0'];

        foreach (['', '+1', '01', '.5', '1.', ' 1', '1 ', 'NaN', 'Infinity', '1e9999'] as $value) {
            yield 'float ' . json_encode($value, JSON_THROW_ON_ERROR) => ['float', $value];
        }

        foreach (['', 'TRUE', 'False', '1', '0', 'yes', ' false'] as $value) {
            yield 'boolean ' . json_encode($value, JSON_THROW_ON_ERROR) => ['boolean', $value];
        }
    }

    public function testNonStringWireValueIsRejectedWithoutBeingCast(): void
    {
        $this->expectException(OperationValueBindingException::class);

        new HttpBoundScalarDecoder()->decode($this->parameter('integer'), 42);
    }

    private function parameter(string $name): ReflectionParameter
    {
        $constructor = new ReflectionClass(ScalarDecodeFixture::class)->getConstructor();
        self::assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        self::fail('Unknown scalar fixture parameter.');
    }
}

final readonly class ScalarDecodeFixture
{
    public function __construct(
        public string $string,
        public int $integer,
        public float $float,
        public bool $boolean,
    ) {}
}
