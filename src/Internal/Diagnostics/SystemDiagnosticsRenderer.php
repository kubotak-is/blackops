<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;

final readonly class SystemDiagnosticsRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    public function render(SystemDiagnosticsReport $report, string|bool $format = 'txt', ?bool $json = null): string
    {
        if ($json !== null) {
            $format = $json ? 'json' : 'txt';
        }
        if (is_bool($format)) {
            $format = $format ? 'json' : 'txt';
        }
        if (!in_array($format, ['txt', 'json'], strict: true)) {
            throw new InvalidArgumentException('Diagnostics report format is unsupported.');
        }

        return $format === 'json' ? $this->json($report) : $this->human($report);
    }

    private function human(SystemDiagnosticsReport $report): string
    {
        $lines = [
            'schemaVersion=1',
            'status=' . $report->status,
            'checkedAt=' . $report->toArray()['checkedAt'],
            'projectionBuildSet=' . $report->projectionBuildSet,
        ];
        foreach ($report->checks as $check) {
            if ($check['family'] !== 'readiness') {
                continue;
            }
            $lines[] = 'readiness.' . $check['code'] . '=' . $check['status'];
        }

        return implode("\n", $lines) . "\n";
    }

    private function json(SystemDiagnosticsReport $report): string
    {
        return $this->codec->canonicalJson($report->toArray()) . "\n";
    }
}
