<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\ScheduleContext;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ScheduleContextTest extends TestCase
{
    public function testNormalizesScheduledAtToUtc(): void
    {
        $context = new ScheduleContext(
            'reports.daily',
            new DateTimeImmutable('2026-07-28 09:00:00', new DateTimeZone('Asia/Tokyo')),
            'Asia/Tokyo',
        );

        self::assertSame('2026-07-28T00:00:00+00:00', $context->scheduledAt()->format(DATE_ATOM));
        self::assertSame('reports.daily', $context->name());
        self::assertSame('Asia/Tokyo', $context->timezone());
    }

    public function testRejectsInvalidIdentityAndTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduleContext('Reports.Daily', new DateTimeImmutable('now'), 'UTC');
    }

    public function testRejectsInvalidTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduleContext('reports.daily', new DateTimeImmutable('now'), 'Local/Host');
    }
}
