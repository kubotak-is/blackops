<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Identifier;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Exception\InvalidIdentifierException;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\IdentifierBehavior;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IdentifierTest extends TestCase
{
    private const VALID_V7 = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const VALID_V7_OTHER = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public static function identifierTypes(): array
    {
        return [
            'OperationId' => [OperationId::class],
            'AttemptId' => [AttemptId::class],
            'JournalRecordId' => [JournalRecordId::class],
            'CorrelationId' => [CorrelationId::class],
            'CausationId' => [CausationId::class],
            'RetentionHoldId' => [RetentionHoldId::class],
            'RetentionPurgeAuditId' => [RetentionPurgeAuditId::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifierTypes')]
    public function testIsFinalReadonlyClassMarkedPublicApi(string $type): void
    {
        $reflection = new ReflectionClass($type);

        self::assertTrue($reflection->isFinal(), $type . ' must be final.');
        self::assertTrue($reflection->isReadOnly(), $type . ' must be readonly.');
        self::assertCount(
            1,
            $reflection->getAttributes(PublicApi::class),
            $type . ' must be marked with #[PublicApi].',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifierTypes')]
    public function testConstructorIsPrivate(string $type): void
    {
        $reflection = new ReflectionClass($type);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor, $type . ' must declare a constructor.');
        self::assertTrue(
            $constructor->isPrivate(),
            $type . ' constructor must be private (not part of the public API).',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifierTypes')]
    public function testFromStringRoundTripsViaToString(string $type): void
    {
        $id = $type::fromString(self::VALID_V7);

        self::assertSame(self::VALID_V7, $id->toString());
        self::assertSame(self::VALID_V7, (string) $id);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifierTypes')]
    public function testFromStringAcceptsUppercaseAndNormalizesToLowercase(string $type): void
    {
        $upper = strtoupper(self::VALID_V7);

        $id = $type::fromString($upper);

        self::assertSame(self::VALID_V7, $id->toString(), 'Canonical form must be lowercase RFC 4122.');
    }

    public function testToStringIsLowercaseRfc4122ForAllTypes(): void
    {
        foreach (self::identifierTypes() as [$type]) {
            self::assertSame(self::VALID_V7, $type::fromString(self::VALID_V7)->toString());
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifierTypes')]
    public function testEqualsReturnsTrueOnlyForSameTypeAndSameValue(string $type): void
    {
        $a = $type::fromString(self::VALID_V7);
        $b = $type::fromString(self::VALID_V7);
        $other = $type::fromString(self::VALID_V7_OTHER);

        self::assertTrue($a->equals($b), 'Same value must be equal.');
        self::assertFalse($a->equals($other), 'Different value must not be equal.');
    }

    public function testEqualsRejectsDifferentIdentifierTypeAtTheTypeLevel(): void
    {
        $operation = OperationId::fromString(self::VALID_V7);
        $attempt = AttemptId::fromString(self::VALID_V7);

        $this->expectException(\TypeError::class);

        $operation->equals($attempt);
    }

    /**
     * @param array<int, array{string|class-string|null}> $sameValueOtherTypeCases
     */
    public static function invalidIdentifierInputs(): array
    {
        return [
            'garbage' => ['not-a-uuid'],
            'too short' => ['019f32ab-2be0-7b38-a0a7-1ab2f968769'],
            'too long' => ['019f32ab-2be0-7b38-a0a7-1ab2f96876977'],
            'hex no dashes' => ['019f32ab2be07b38a0a71ab2f9687697'],
            'empty string' => [''],
            'uuid version 4' => ['a3e4d816-cb25-4a7e-8b9e-9f1c4d7b6a52'],
            'uuid version 1' => ['123e4567-e89b-12d3-a456-426614174000'],
            'uuid version 6' => ['1ec36c8f-1566-6710-99b5-e07acf1c1b00'],
            'nil uuid' => ['00000000-0000-0000-0000-000000000000'],
            'valid shape but variant 0' => ['019f32ab-2be0-7b38-0a07-1ab2f9687697'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdentifierInputs')]
    public function testFromStringRejectsInvalidFormatOrVersion(string $invalid): void
    {
        $this->expectException(InvalidIdentifierException::class);

        OperationId::fromString($invalid);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdentifierInputs')]
    public function testExceptionMessageDoesNotIncludeTheInputValue(string $invalid): void
    {
        try {
            OperationId::fromString($invalid);
            self::fail('Expected InvalidIdentifierException was not thrown.');
        } catch (InvalidIdentifierException $exception) {
            $message = $exception->getMessage();

            self::assertStringContainsString(
                'requires a valid UUID version 7',
                $message,
                'Exception message must use the fixed safe template.',
            );

            if ($invalid !== '') {
                self::assertStringNotContainsString(
                    $invalid,
                    $message,
                    'Exception message must not include the offending input value.',
                );
            }
        }
    }

    public function testInvalidIdentifierExceptionExtendsInvalidArgumentException(): void
    {
        $reflection = new ReflectionClass(InvalidIdentifierException::class);

        self::assertTrue($reflection->isFinal(), 'InvalidIdentifierException must be final.');
        self::assertTrue(
            $reflection->isSubclassOf(\InvalidArgumentException::class),
            'InvalidIdentifierException must extend \\InvalidArgumentException.',
        );
        self::assertCount(
            1,
            $reflection->getAttributes(PublicApi::class),
            'InvalidIdentifierException must be marked with #[PublicApi].',
        );
    }

    public function testIdentifierBehaviorIsNotMarkedPublicApi(): void
    {
        $reflection = new ReflectionClass(IdentifierBehavior::class);

        self::assertTrue($reflection->isTrait(), 'IdentifierBehavior must be a trait.');
        self::assertSame(
            [],
            $reflection->getAttributes(PublicApi::class),
            'IdentifierBehavior is an internal implementation detail and must not be #[PublicApi].',
        );
    }

    public function testAllIdentifierTypesAreDistinctPhpTypes(): void
    {
        $types = [
            OperationId::class,
            AttemptId::class,
            JournalRecordId::class,
            CorrelationId::class,
            CausationId::class,
            RetentionHoldId::class,
            RetentionPurgeAuditId::class,
        ];

        self::assertSameSize(array_unique($types), $types, 'Identifier types must be distinct.');
    }
}
