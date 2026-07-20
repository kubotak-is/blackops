<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\InvalidDigestWeek;
use App\Domain\Board\IsoWeek;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsoWeekTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function validWeeks(): iterable
    {
        yield 'year boundary' => ['2020-W53', '2020-12-28T00:00:00+00:00', '2021-01-04T00:00:00+00:00'];
        yield 'first week' => ['2021-W01', '2021-01-04T00:00:00+00:00', '2021-01-11T00:00:00+00:00'];
        yield 'leap year' => ['2024-W09', '2024-02-26T00:00:00+00:00', '2024-03-04T00:00:00+00:00'];
    }

    #[DataProvider('validWeeks')]
    public function testParsesCanonicalWeekIntoUtcHalfOpenRange(string $value, string $start, string $end): void
    {
        date_default_timezone_set('Pacific/Honolulu');
        $week = IsoWeek::fromString($value);

        self::assertSame($value, $week->value());
        self::assertSame($start, $week->startsAt()->format('Y-m-d\TH:i:sP'));
        self::assertSame($end, $week->endsAt()->format('Y-m-d\TH:i:sP'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidWeeks(): iterable
    {
        foreach (['2021-W53', '2026-W00', '2026-W54', '2026-w01', '２０２６-W01', '2026-W1', 'raw-secret'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('invalidWeeks')]
    public function testRejectsMalformedAndNonexistentWeeksWithoutEchoingInput(string $value): void
    {
        try {
            IsoWeek::fromString($value);
            self::fail('Expected invalid week.');
        } catch (InvalidDigestWeek $exception) {
            self::assertStringNotContainsString($value, $exception->getMessage());
        }
    }
}
