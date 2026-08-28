<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Internal\Diagnostics\SystemDiagnosticsNormalizer;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReadResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemDiagnosticsNormalizerTest extends TestCase
{
    public function testNormalizesProjectionAndReadinessToFixedOrder(): void
    {
        $report = new SystemDiagnosticsNormalizer()->normalize(
            checkedAt: new DateTimeImmutable('2026-08-27T15:00:00+09:00'),
            projection: $this->available(),
            readiness: $this->readiness([
                'runtime_services',
                'database',
                'compiled_artifact',
                'storage_key_provider',
                'migration_compatibility',
                'runtime_configuration',
            ]),
        );

        self::assertTrue($report->isPassing());
        self::assertSame(
            [
                ['code' => 'available', 'family' => 'projection_build_set', 'status' => 'pass'],
                ['code' => 'compiled_artifact', 'family' => 'readiness', 'status' => 'pass'],
                ['code' => 'runtime_configuration', 'family' => 'readiness', 'status' => 'pass'],
                ['code' => 'database', 'family' => 'readiness', 'status' => 'pass'],
                ['code' => 'migration_compatibility', 'family' => 'readiness', 'status' => 'pass'],
                ['code' => 'storage_key_provider', 'family' => 'readiness', 'status' => 'pass'],
                ['code' => 'runtime_services', 'family' => 'readiness', 'status' => 'pass'],
            ],
            $report->checks,
        );
        self::assertSame('2026-08-27T06:00:00.000000Z', $report->toArray()['checkedAt']);
    }

    public function testEveryProjectionFailureIsAClosedFailureRow(): void
    {
        foreach ([
            ProjectionBuildSetRecoveryStatus::ABSENT,
            ProjectionBuildSetRecoveryStatus::MALFORMED,
            ProjectionBuildSetRecoveryStatus::UNSUPPORTED,
            ProjectionBuildSetRecoveryStatus::STALE,
            ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH,
            'configuration_invalid',
            'read_failed',
        ] as $status) {
            $report = new SystemDiagnosticsNormalizer()->normalize(
                new DateTimeImmutable('2026-08-27T15:00:00Z'),
                $status,
                $this->readiness(),
            );

            self::assertFalse(
                $report->isPassing(),
                $status instanceof ProjectionBuildSetRecoveryStatus ? $status->value : $status,
            );
            self::assertSame(
                $status instanceof ProjectionBuildSetRecoveryStatus ? $status->value : $status,
                $report->projectionBuildSet,
            );
            self::assertSame('fail', $report->checks[0]['status']);
        }
    }

    public function testReadinessFailuresContractToOneSafeRow(): void
    {
        foreach (['query_unavailable', 'query_failed', 'query_invalid'] as $failure) {
            $report = new SystemDiagnosticsNormalizer()->normalize(
                new DateTimeImmutable('2026-08-27T15:00:00Z'),
                ProjectionBuildSetRecoveryStatus::AVAILABLE,
                readinessFailure: $failure,
            );

            self::assertFalse($report->isPassing(), $failure);
            self::assertSame(
                [['code' => $failure, 'family' => 'readiness', 'status' => 'fail']],
                array_slice($report->checks, 1),
                $failure,
            );
        }
    }

    public function testWrongKindCardinalityDuplicateUnknownMissingAndAggregateMismatchAreInvalid(): void
    {
        $cases = [
            new OperationalHealthReport(
                OperationalHealthKind::Liveness,
                OperationalHealthStatus::Pass,
                new DateTimeImmutable('now'),
                [],
            ),
            new OperationalHealthReport(
                OperationalHealthKind::Readiness,
                OperationalHealthStatus::Pass,
                new DateTimeImmutable('now'),
                array_map(
                    OperationalHealthCheck::pass(...),
                    array_slice(SystemDiagnosticsNormalizer::READINESS_CODES, 0, 5),
                ),
            ),
            $this->readiness([
                'compiled_artifact',
                'runtime_configuration',
                'database',
                'migration_compatibility',
                'storage_key_provider',
                'compiled_artifact',
            ]),
            $this->readiness([
                'compiled_artifact',
                'runtime_configuration',
                'database',
                'migration_compatibility',
                'storage_key_provider',
                'unknown',
            ]),
            new OperationalHealthReport(
                OperationalHealthKind::Readiness,
                OperationalHealthStatus::Fail,
                new DateTimeImmutable('now'),
                array_map(OperationalHealthCheck::pass(...), SystemDiagnosticsNormalizer::READINESS_CODES),
            ),
        ];

        foreach ($cases as $case) {
            $report = new SystemDiagnosticsNormalizer()->normalize(
                new DateTimeImmutable('2026-08-27T15:00:00Z'),
                ProjectionBuildSetRecoveryStatus::AVAILABLE,
                $case,
            );
            self::assertSame('query_invalid', $report->checks[1]['code']);
            self::assertCount(2, $report->checks);
            self::assertFalse($report->isPassing());
        }

        $failing = $this->readiness(status: OperationalHealthStatus::Fail);
        $report = new SystemDiagnosticsNormalizer()->normalize(
            new DateTimeImmutable('2026-08-27T15:00:00Z'),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            $failing,
        );
        self::assertSame('compiled_artifact', $report->checks[1]['code']);
        self::assertFalse($report->isPassing());
    }

    public function testMissingQueryDefaultsToUnavailableWithoutExposingInput(): void
    {
        $report = new SystemDiagnosticsNormalizer()->normalize(
            new DateTimeImmutable('2026-08-27T15:00:00Z'),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
        );

        self::assertSame('query_unavailable', $report->checks[1]['code']);
        self::assertCount(2, $report->checks);
        self::assertStringNotContainsString('password', json_encode($report->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testExtraCardinalityIsRejectedBeforeTraversingNonCheckEntries(): void
    {
        $checks = array_map(OperationalHealthCheck::pass(...), SystemDiagnosticsNormalizer::READINESS_CODES);
        $checks[] = 'not-a-health-check';
        $readiness = new OperationalHealthReport(
            OperationalHealthKind::Readiness,
            OperationalHealthStatus::Pass,
            new DateTimeImmutable('now'),
            $checks,
        );

        $report = new SystemDiagnosticsNormalizer()->normalize(
            new DateTimeImmutable('2026-08-27T15:00:00Z'),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            $readiness,
        );

        self::assertSame('query_invalid', $report->checks[1]['code']);
        self::assertCount(2, $report->checks);
        self::assertStringNotContainsString('not-a-health-check', json_encode($report->toArray(), JSON_THROW_ON_ERROR));
    }

    private function available(): ProjectionBuildSetRecoveryResult
    {
        return new ProjectionBuildSetRecoveryResult(
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            new ProjectionBuildSetReadResult('build', str_repeat('a', 64), []),
        );
    }

    /** @param list<string>|null $order */
    private function readiness(
        ?array $order = null,
        OperationalHealthStatus $status = OperationalHealthStatus::Pass,
    ): OperationalHealthReport {
        $order ??= SystemDiagnosticsNormalizer::READINESS_CODES;
        $checks = array_map(static fn(string $code): OperationalHealthCheck => $status === OperationalHealthStatus::Pass
            ? OperationalHealthCheck::pass($code)
            : OperationalHealthCheck::fail($code), $order);

        return new OperationalHealthReport(
            OperationalHealthKind::Readiness,
            $status,
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            $checks,
        );
    }
}
