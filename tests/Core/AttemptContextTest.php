<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttemptContextTest extends TestCase
{
    private const VALID_V7 = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testIsFinalReadonlyClassMarkedPublicApi(): void
    {
        $reflection = new ReflectionClass(AttemptContext::class);

        self::assertTrue($reflection->isFinal(), 'AttemptContext must be final.');
        self::assertTrue($reflection->isReadOnly(), 'AttemptContext must be readonly.');
        self::assertCount(
            1,
            $reflection->getAttributes(PublicApi::class),
            'AttemptContext must be marked with #[PublicApi].',
        );
    }

    public function testGettersReturnConstructorValues(): void
    {
        $id = AttemptId::fromString(self::VALID_V7);
        $startedAt = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('UTC'));

        $attempt = new AttemptContext($id, 1, $startedAt);

        self::assertSame($id, $attempt->id());
        self::assertSame(1, $attempt->number());
        self::assertSame($startedAt, $attempt->startedAt());
    }

    public function testStartedAtIsNormalizedToUtc(): void
    {
        $tokyoTime = new DateTimeImmutable('2026-07-02T21:34:56.123456', new DateTimeZone('Asia/Tokyo'));

        $attempt = new AttemptContext(AttemptId::fromString(self::VALID_V7), 3, $tokyoTime);

        $normalized = $attempt->startedAt();

        self::assertSame('UTC', $normalized->getTimezone()->getName());
        self::assertSame('2026-07-02T12:34:56.123456Z', $normalized->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testAlreadyUtcTimeIsPreservedWithoutConversionLoss(): void
    {
        $utcTime = new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC'));

        $attempt = new AttemptContext(AttemptId::fromString(self::VALID_V7), 1, $utcTime);

        self::assertSame('UTC', $attempt->startedAt()->getTimezone()->getName());
        self::assertSame('2026-07-02T12:34:56.123456Z', $attempt->startedAt()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testAttemptNumberOneIsAccepted(): void
    {
        $attempt = new AttemptContext(
            AttemptId::fromString(self::VALID_V7),
            1,
            new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC')),
        );

        self::assertSame(1, $attempt->number());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidAttemptNumbers(): array
    {
        return [
            'zero' => [0],
            'negative one' => [-1],
            'deeply negative' => [-99],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAttemptNumbers')]
    public function testAttemptNumberBelowOneIsRejected(int $number): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AttemptContext(
            AttemptId::fromString(self::VALID_V7),
            $number,
            new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC')),
        );
    }

    public function testRejectedExceptionMessageDoesNotLeakInput(): void
    {
        try {
            new AttemptContext(
                AttemptId::fromString(self::VALID_V7),
                0,
                new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC')),
            );
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString(
                '0',
                $exception->getMessage(),
                'Exception message must not depend on the offending value.',
            );
        }
    }
}
