<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Time;

use BlackOps\Core\Time\TimeCodec;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TimeCodecTest extends TestCase
{
    public function testToUtcNormalizesNonUtcTimeToUtc(): void
    {
        $codec = new TimeCodec();
        $tokyo = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('Asia/Tokyo'));

        $utc = $codec->toUtc($tokyo);

        self::assertSame('UTC', $utc->getTimezone()->getName());
        self::assertSame('2026-07-02T03:34:56.123456Z', $utc->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame($tokyo->getTimestamp(), $utc->getTimestamp(), 'Normalization must preserve the instant.');
    }

    public function testToUtcKeepsUtcTimeUnchanged(): void
    {
        $codec = new TimeCodec();
        $utc = new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC'));
        $normalized = $codec->toUtc($utc);

        self::assertSame('UTC', $normalized->getTimezone()->getName());
        self::assertSame('2026-07-02T12:34:56.123456Z', $normalized->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testFormatProducesRfc3339WithMicrosecondsAndZSuffix(): void
    {
        $codec = new TimeCodec();
        $utc = new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC'));

        self::assertSame('2026-07-02T12:34:56.123456Z', $codec->format($utc));
    }

    public function testFormatPadsMissingMicrosecondsToSixDigits(): void
    {
        $codec = new TimeCodec();
        $utc = new DateTimeImmutable('2026-07-02T12:34:56', new DateTimeZone('UTC'));

        self::assertSame('2026-07-02T12:34:56.000000Z', $codec->format($utc));
    }

    public function testFormatNormalizesNonUtcInputToUtc(): void
    {
        $codec = new TimeCodec();
        $tokyo = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('Asia/Tokyo'));

        self::assertSame('2026-07-02T03:34:56.123456Z', $codec->format($tokyo));
    }

    public function testClockDerivedTimeCanBeNormalizedToUtcAndFormatted(): void
    {
        $codec = new TimeCodec();
        $fixed = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('Asia/Tokyo'));
        $clock = new class($fixed) implements ClockInterface {
            public function __construct(
                private readonly DateTimeImmutable $now,
            ) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $normalized = $codec->toUtc($clock->now());

        self::assertSame('UTC', $normalized->getTimezone()->getName());
        self::assertSame('2026-07-02T03:34:56.123456Z', $codec->format($clock->now()));
    }
}
