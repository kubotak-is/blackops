<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Observability;

use BlackOps\Internal\Observability\ApplicationOperationalHealthChecks;
use BlackOps\Internal\Observability\CallbackOperationalHealthCheck;
use BlackOps\Internal\Observability\DefaultOperationalHealthQuery;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQueryFactory;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class DefaultOperationalHealthQueryTest extends TestCase
{
    public function testLivenessDoesNotEvaluateReadinessDependencies(): void
    {
        $called = false;
        $query = new DefaultOperationalHealthQuery([
            new CallbackOperationalHealthCheck('database', static function () use (&$called): bool {
                $called = true;
                return false;
            }),
        ], new FixedHealthClock());

        $report = $query->check(OperationalHealthKind::Liveness);

        self::assertSame(OperationalHealthStatus::Pass, $report->status);
        self::assertSame([], $report->checks);
        self::assertFalse($called);
        self::assertSame(
            [
                'schemaVersion' => 1,
                'kind' => 'liveness',
                'status' => 'pass',
                'checkedAt' => '2026-08-09T01:02:03.123456Z',
                'checks' => [],
            ],
            $report->toArray(),
        );
    }

    public function testReadinessReturnsOnlySafeFiniteChecksAndConvertsProviderFailure(): void
    {
        $query = new DefaultOperationalHealthQuery([
            new CallbackOperationalHealthCheck('database', static fn(): bool => true),
            new CallbackOperationalHealthCheck('migration', static function (): bool {
                throw new RuntimeException('dsn=secret; password=secret');
            }),
        ], new FixedHealthClock());

        $report = $query->check(OperationalHealthKind::Readiness);

        self::assertSame(OperationalHealthStatus::Fail, $report->status);
        self::assertSame(
            [
                ['code' => 'database', 'status' => 'pass'],
                ['code' => 'migration', 'status' => 'fail'],
            ],
            $report->toArray()['checks'],
        );
        self::assertStringNotContainsString('secret', json_encode($report->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testInvalidProviderCodeIsNormalizedToSafeCheck(): void
    {
        $query = new DefaultOperationalHealthQuery([
            new CallbackOperationalHealthCheck('invalid code', static fn(): bool => true),
        ], new FixedHealthClock());

        self::assertSame(
            [['code' => 'check.invalid', 'status' => 'fail']],
            $query->check(OperationalHealthKind::Readiness)->toArray()['checks'],
        );
    }

    public function testApplicationReadinessUsesStableBoundedCategories(): void
    {
        $callbacks = [];
        foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
            $callbacks[$code] = static fn(): bool => true;
        }
        $report = new DefaultOperationalHealthQuery(
            ApplicationOperationalHealthChecks::fromCallbacks($callbacks),
            new FixedHealthClock(),
        )->check(OperationalHealthKind::Readiness);

        self::assertSame('pass', $report->toArray()['status']);
        self::assertSame(ApplicationOperationalHealthChecks::codes(), array_column(
            $report->toArray()['checks'],
            'code',
        ));
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FixedHealthClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-09T01:02:03.123456Z');
    }
}
