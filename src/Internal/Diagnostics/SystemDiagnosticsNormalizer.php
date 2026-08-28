<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;

/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
final readonly class SystemDiagnosticsNormalizer
{
    /** @var list<string> */
    public const array READINESS_CODES = [
        'compiled_artifact',
        'runtime_configuration',
        'database',
        'migration_compatibility',
        'storage_key_provider',
        'runtime_services',
    ];

    /** @var list<string> */
    private const array PROJECTION_CODES = [
        'available',
        'absent',
        'malformed',
        'unsupported',
        'stale',
        'build_mismatch',
        'configuration_invalid',
        'read_failed',
    ];

    /** @var list<string> */
    private const array READINESS_FAILURE_CODES = ['query_unavailable', 'query_failed', 'query_invalid'];

    /**
     * @param ProjectionBuildSetRecoveryResult|ProjectionBuildSetRecoveryStatus|string $projection
     */
    public function normalize(
        DateTimeImmutable $checkedAt,
        ProjectionBuildSetRecoveryResult|ProjectionBuildSetRecoveryStatus|string $projection,
        ?OperationalHealthReport $readiness = null,
        ?string $readinessFailure = null,
    ): SystemDiagnosticsReport {
        $projectionCode = $this->projectionCode($projection);
        $readinessChecks = $this->readinessChecks($readiness, $readinessFailure);
        $status = $projectionCode === 'available'
        && array_all($readinessChecks, static fn(array $check): bool => $check['status'] === 'pass')
            ? 'pass'
            : 'fail';

        return new SystemDiagnosticsReport(
            checkedAt: $checkedAt,
            status: $status,
            projectionBuildSet: $projectionCode,
            checks: [
                [
                    'code' => $projectionCode,
                    'family' => 'projection_build_set',
                    'status' => $projectionCode === 'available' ? 'pass' : 'fail',
                ],
                ...$readinessChecks,
            ],
        );
    }

    /**
     * @param ProjectionBuildSetRecoveryResult|ProjectionBuildSetRecoveryStatus|string $projection
     */
    private function projectionCode(ProjectionBuildSetRecoveryResult|ProjectionBuildSetRecoveryStatus|string $projection): string
    {
        if ($projection instanceof ProjectionBuildSetRecoveryResult) {
            $projection = $projection->status;
        }
        if ($projection instanceof ProjectionBuildSetRecoveryStatus) {
            return $projection->value;
        }
        if (in_array($projection, self::PROJECTION_CODES, strict: true)) {
            return $projection;
        }

        return 'configuration_invalid';
    }

    /**
     * @return list<array{code: string, family: string, status: string}>
     */
    private function readinessChecks(?OperationalHealthReport $readiness, ?string $readinessFailure): array
    {
        if ($readinessFailure !== null) {
            return [$this->failureCheck($readinessFailure)];
        }
        if ($readiness === null) {
            return [$this->failureCheck('query_unavailable')];
        }
        if ($readiness->kind !== OperationalHealthKind::Readiness) {
            return [$this->failureCheck('query_invalid')];
        }

        $checks = $readiness->checks;
        if (count($checks) !== count(self::READINESS_CODES)) {
            return [$this->failureCheck('query_invalid')];
        }

        /** @var array<string, string> $byCode */
        $byCode = [];
        foreach ($checks as $check) {
            if (!$check instanceof OperationalHealthCheck) {
                return [$this->failureCheck('query_invalid')];
            }
            if (!in_array($check->code, self::READINESS_CODES, strict: true)) {
                return [$this->failureCheck('query_invalid')];
            }
            if (array_key_exists($check->code, $byCode)) {
                return [$this->failureCheck('query_invalid')];
            }
            $byCode[$check->code] = $check->status->value;
        }

        if (count($byCode) !== count(self::READINESS_CODES)) {
            return [$this->failureCheck('query_invalid')];
        }
        foreach (self::READINESS_CODES as $code) {
            if (!array_key_exists($code, $byCode)) {
                return [$this->failureCheck('query_invalid')];
            }
        }

        $expectedStatus = array_all(
            $byCode,
            static fn(string $status): bool => $status === OperationalHealthStatus::Pass->value,
        )
            ? OperationalHealthStatus::Pass
            : OperationalHealthStatus::Fail;
        if ($readiness->status !== $expectedStatus) {
            return [$this->failureCheck('query_invalid')];
        }

        $normalized = [];
        foreach (self::READINESS_CODES as $code) {
            $normalized[] = [
                'code' => $code,
                'family' => 'readiness',
                'status' => $byCode[$code],
            ];
        }

        return $normalized;
    }

    /** @return array{code: string, family: string, status: string} */
    private function failureCheck(string $code): array
    {
        if (!in_array($code, self::READINESS_FAILURE_CODES, strict: true)) {
            $code = 'query_invalid';
        }

        return [
            'code' => $code,
            'family' => 'readiness',
            'status' => 'fail',
        ];
    }
}
