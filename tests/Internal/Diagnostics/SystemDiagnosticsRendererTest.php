<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Internal\Diagnostics\SystemDiagnosticsNormalizer;
use BlackOps\Internal\Diagnostics\SystemDiagnosticsRenderer;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemDiagnosticsRendererTest extends TestCase
{
    public function testHumanOutputIsFixedOrderAndHasOneFinalLineFeed(): void
    {
        $report = $this->report();

        self::assertSame(
            "schemaVersion=1\n"
            . "status=pass\n"
            . "checkedAt=2026-08-27T15:00:00.000000Z\n"
            . "projectionBuildSet=available\n"
            . "readiness.compiled_artifact=pass\n"
            . "readiness.runtime_configuration=pass\n"
            . "readiness.database=pass\n"
            . "readiness.migration_compatibility=pass\n"
            . "readiness.storage_key_provider=pass\n"
            . "readiness.runtime_services=pass\n",
            new SystemDiagnosticsRenderer()->render($report),
        );
    }

    public function testJsonUsesTheExistingCanonicalJsonCodecAndClosedShape(): void
    {
        $bytes = new SystemDiagnosticsRenderer()->render($this->report(), json: true);

        self::assertSame(
            '{"checkedAt":"2026-08-27T15:00:00.000000Z","checks":[{"code":"available",'
            . '"family":"projection_build_set","status":"pass"},{"code":"compiled_artifact",'
            . '"family":"readiness","status":"pass"},{"code":"runtime_configuration",'
            . '"family":"readiness","status":"pass"},{"code":"database","family":"readiness",'
            . '"status":"pass"},{"code":"migration_compatibility","family":"readiness",'
            . '"status":"pass"},{"code":"storage_key_provider","family":"readiness",'
            . '"status":"pass"},{"code":"runtime_services","family":"readiness","status":"pass"}],'
            . '"schemaVersion":1,"status":"pass"}'
            . "\n",
            $bytes,
        );
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringNotContainsString("\xEF\xBB\xBF", $bytes);
        self::assertSame(1, substr_count($bytes, "\n"));
    }

    public function testContractedReadinessFailureReplacesSixRows(): void
    {
        $normalizer = new SystemDiagnosticsNormalizer();
        $report = $normalizer->normalize(
            new DateTimeImmutable('2026-08-27T15:00:00Z'),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            readinessFailure: 'query_failed',
        );

        self::assertSame(
            "schemaVersion=1\n"
            . "status=fail\n"
            . "checkedAt=2026-08-27T15:00:00.000000Z\n"
            . "projectionBuildSet=available\n"
            . "readiness.query_failed=fail\n",
            new SystemDiagnosticsRenderer()->render($report),
        );
        self::assertStringContainsString('"code":"query_failed"', new SystemDiagnosticsRenderer()->render(
            $report,
            json: true,
        ));
        self::assertStringNotContainsString('compiled_artifact', new SystemDiagnosticsRenderer()->render(
            $report,
            json: true,
        ));
    }

    private function report(): \BlackOps\Internal\Diagnostics\SystemDiagnosticsReport
    {
        return new SystemDiagnosticsNormalizer()->normalize(
            new DateTimeImmutable('2026-08-28T00:00:00+09:00'),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            new OperationalHealthReport(
                OperationalHealthKind::Readiness,
                OperationalHealthStatus::Pass,
                new DateTimeImmutable('1999-01-01T00:00:00Z'),
                array_map(
                    OperationalHealthCheck::pass(...),
                    array_reverse(SystemDiagnosticsNormalizer::READINESS_CODES),
                ),
            ),
        );
    }
}
