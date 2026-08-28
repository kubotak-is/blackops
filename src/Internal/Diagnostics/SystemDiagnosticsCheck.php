<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Internal\Observability\SystemClock;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SystemDiagnosticsCheck
{
    public function __construct(
        private ProjectionBuildSetStatusSource $projection,
        private ?OperationalHealthQuery $readiness = null,
        private ClockInterface $clock = new SystemClock(),
        private SystemDiagnosticsNormalizer $normalizer = new SystemDiagnosticsNormalizer(),
    ) {}

    public function check(): SystemDiagnosticsReport
    {
        $checkedAt = $this->clock->now();
        $projection = $this->projectionStatus();
        [$readiness, $readinessFailure] = $this->readinessReport();

        return $this->normalizer->normalize(
            checkedAt: $checkedAt,
            projection: $projection,
            readiness: $readiness,
            readinessFailure: $readinessFailure,
        );
    }

    public function run(): SystemDiagnosticsReport
    {
        return $this->check();
    }

    private function projectionStatus(): ProjectionBuildSetRecoveryStatus|string
    {
        try {
            $result = $this->projection->withStatus(static fn(ProjectionBuildSetRecoveryResult $recovery): ProjectionBuildSetRecoveryStatus => $recovery->status);
            if ($result instanceof ProjectionBuildSetRecoveryStatus) {
                return $result;
            }

            return 'configuration_invalid';
        } catch (ProjectionBuildSetValidationException) {
            return 'configuration_invalid';
        } catch (ProjectionBuildSetFilesystemException) {
            return 'read_failed';
        }
    }

    /** @return array{0: ?\BlackOps\Observability\OperationalHealthReport, 1: ?string} */
    private function readinessReport(): array
    {
        if ($this->readiness === null) {
            return [null, 'query_unavailable'];
        }

        try {
            return [$this->readiness->check(OperationalHealthKind::Readiness), null];
        } catch (Throwable) {
            return [null, 'query_failed'];
        }
    }
}
