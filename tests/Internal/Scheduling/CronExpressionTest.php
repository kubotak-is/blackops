<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Internal\Scheduling\CronExpression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CronExpressionTest extends TestCase
{
    public function testParsesNumericWildcardListRangeAndStepFields(): void
    {
        $cron = CronExpression::parse('*/5 0,12 1-15 1-12 1-5');

        self::assertSame([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55], $cron->minute->values);
        self::assertSame([0, 12], $cron->hour->values);
        self::assertTrue($cron->usesDayOfMonthDayOfWeekOrSemantics());
    }

    public function testRecordsPosixDayOfMonthDayOfWeekOrSemantics(): void
    {
        $cron = CronExpression::parse('0 0 1 * 1');
        self::assertTrue($cron->usesDayOfMonthDayOfWeekOrSemantics());
        self::assertFalse($cron->dayOfMonth->wildcard);
        self::assertFalse($cron->dayOfWeek->wildcard);
        self::assertFalse(CronExpression::parse('0 0 * * 1')->usesDayOfMonthDayOfWeekOrSemantics());
    }

    public function testNormalizesSundaySevenToZeroAndDeduplicates(): void
    {
        self::assertSame([0], CronExpression::parse('0 0 * * 7')->dayOfWeek->values);
        self::assertSame([0], CronExpression::parse('0 0 * * 0,7')->dayOfWeek->values);
    }

    #[DataProvider('invalidExpressions')]
    public function testRejectsUnsupportedExpressions(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);
        CronExpression::parse($expression);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidExpressions(): iterable
    {
        yield 'seconds' => ['0 0 0 * * *'];
        yield 'nickname' => ['@daily'];
        yield 'month name' => ['0 0 * JAN *'];
        yield 'reverse range' => ['0 0 10-1 * *'];
        yield 'single value step' => ['5/2 * * * *'];
        yield 'step zero' => ['*/0 * * * *'];
    }
}
