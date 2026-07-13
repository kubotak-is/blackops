<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Validation;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Validation\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ViolationTest extends TestCase
{
    public function testContainsOnlySafeValidationMetadata(): void
    {
        $violation = new Violation('accessToken', 'not_blank', 'validation.not_blank');
        $reflection = new ReflectionClass($violation);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['field', 'rule', 'code'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame('accessToken', $violation->field);
        self::assertSame('not_blank', $violation->rule);
        self::assertSame('validation.not_blank', $violation->code);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidMetadata(): iterable
    {
        yield 'empty field' => ['', 'not_blank', 'validation.not_blank'];
        yield 'unsafe field' => ['access-token', 'not_blank', 'validation.not_blank'];
        yield 'empty rule' => ['field', '', 'validation.not_blank'];
        yield 'unstable rule' => ['field', 'NotBlank', 'validation.not_blank'];
        yield 'empty code' => ['field', 'not_blank', ''];
        yield 'unsafe code' => ['field', 'not_blank', 'SECRET VALUE'];
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(string $field, string $rule, string $code): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Violation($field, $rule, $code);
    }
}
