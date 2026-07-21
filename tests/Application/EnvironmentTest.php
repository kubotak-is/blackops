<?php

declare(strict_types=1);

namespace BlackOps\Tests\Application;

use BlackOps\Application\Environment;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class EnvironmentTest extends TestCase
{
    public function testIsAReadonlyPublicApiWithOnlyTypedAccessors(): void
    {
        $reflection = new ReflectionClass(Environment::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));

        $methods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        );
        sort($methods);

        self::assertSame(['__construct', 'bool', 'int', 'optionalString', 'positiveInt', 'string'], $methods);
    }

    public function testReadsStringsAndPreservesEmptyDefinedValue(): void
    {
        $environment = new Environment(['DEFINED' => 'value', 'EMPTY' => '']);

        self::assertSame('value', $environment->string('DEFINED'));
        self::assertSame('', $environment->string('EMPTY', 'fallback'));
        self::assertSame('', $environment->optionalString('EMPTY'));
        self::assertNull($environment->optionalString('MISSING'));
        self::assertSame('fallback', $environment->string('MISSING', 'fallback'));
    }

    public function testCopiesConstructorInput(): void
    {
        $variables = ['VALUE' => 'before'];
        $environment = new Environment($variables);
        $variables['VALUE'] = 'after';

        self::assertSame('before', $environment->string('VALUE'));
    }

    #[TestWith(['0', 0])]
    #[TestWith(['42', 42])]
    #[TestWith(['-42', -42])]
    public function testReadsCanonicalIntegers(string $raw, int $expected): void
    {
        self::assertSame($expected, new Environment(['VALUE' => $raw])->int('VALUE'));
    }

    #[TestWith(['+1'])]
    #[TestWith(['01'])]
    #[TestWith(['-0'])]
    #[TestWith([' 1'])]
    #[TestWith(['1.0'])]
    #[TestWith(['1e2'])]
    public function testRejectsNonCanonicalOrOutOfRangeIntegersWithoutReflectingRawValue(string $raw): void
    {
        try {
            new Environment(['VALUE' => $raw])->int('VALUE', 99);
            self::fail('Expected invalid integer environment value.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('VALUE', $exception->getMessage());
            self::assertStringContainsString('integer', $exception->getMessage());
            self::assertStringNotContainsString($raw, $exception->getMessage());
        }
    }

    public function testReadsPositiveIntegersAndValidatesDefault(): void
    {
        $environment = new Environment(['VALUE' => '12']);

        self::assertSame(12, $environment->positiveInt('VALUE'));
        self::assertSame(PHP_INT_MAX, new Environment(['VALUE' => (string) PHP_INT_MAX])->positiveInt('VALUE'));
        self::assertSame(7, $environment->positiveInt('MISSING', 7));

        foreach (['0', '-1', '01', PHP_INT_MAX . '0'] as $raw) {
            try {
                new Environment(['VALUE' => $raw])->positiveInt('VALUE', 9);
                self::fail('Expected invalid positive integer environment value.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($raw, $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        new Environment([])->positiveInt('MISSING', 0);
    }

    #[TestWith(['true', true])]
    #[TestWith(['TRUE', true])]
    #[TestWith(['1', true])]
    #[TestWith(['false', false])]
    #[TestWith(['FaLsE', false])]
    #[TestWith(['0', false])]
    public function testReadsCanonicalBooleans(string $raw, bool $expected): void
    {
        self::assertSame($expected, new Environment(['VALUE' => $raw])->bool('VALUE'));
    }

    public function testRejectsUnknownBooleanWithoutFallingBackOrReflectingRawValue(): void
    {
        $raw = 'secret-yes';

        try {
            new Environment(['VALUE' => $raw])->bool('VALUE', true);
            self::fail('Expected invalid boolean environment value.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('VALUE', $exception->getMessage());
            self::assertStringContainsString('boolean', $exception->getMessage());
            self::assertStringNotContainsString($raw, $exception->getMessage());
        }
    }

    public function testUsesTypedDefaultsOnlyForMissingVariables(): void
    {
        $environment = new Environment([]);

        self::assertSame(-2, $environment->int('INTEGER', -2));
        self::assertTrue($environment->bool('BOOLEAN', true));
        self::assertSame(PHP_INT_MIN, new Environment(['VALUE' => (string) PHP_INT_MIN])->int('VALUE'));

        foreach ([PHP_INT_MAX . '0', PHP_INT_MIN . '0'] as $raw) {
            try {
                new Environment(['VALUE' => $raw])->int('VALUE');
                self::fail('Expected integer outside the PHP range to fail.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($raw, $exception->getMessage());
            }
        }

        foreach (['STRING', 'INTEGER', 'POSITIVE', 'BOOLEAN'] as $name) {
            try {
                match ($name) {
                    'STRING' => $environment->string($name),
                    'INTEGER' => $environment->int($name),
                    'POSITIVE' => $environment->positiveInt($name),
                    'BOOLEAN' => $environment->bool($name),
                };
                self::fail('Expected missing environment value.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($name, $exception->getMessage());
            }
        }
    }

    public function testRejectsInvalidConstructorEntriesAndEmptyAccessorNamesWithoutRawValue(): void
    {
        foreach ([[0 => 'value'], ['SECRET' => ['plain-secret']]] as $variables) {
            try {
                new Environment($variables);
                self::fail('Expected invalid environment snapshot.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString('plain-secret', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        new Environment([])->string('');
    }
}
