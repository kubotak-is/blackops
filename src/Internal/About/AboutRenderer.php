<?php

declare(strict_types=1);

namespace BlackOps\Internal\About;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;

final readonly class AboutRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    public function render(AboutReport $report, string|bool $format = 'txt', ?bool $json = null): string
    {
        if ($json !== null) {
            $format = $json ? 'json' : 'txt';
        }
        if (is_bool($format)) {
            $format = $format ? 'json' : 'txt';
        }
        if (!in_array($format, ['txt', 'json'], strict: true)) {
            throw new InvalidArgumentException('About report format is unsupported.');
        }

        return $format === 'json' ? $this->json($report) : $this->human($report);
    }

    private function human(AboutReport $report): string
    {
        $metadata = $report->metadata;
        $lines = [
            'schemaVersion=1',
            'package.name=' . AboutMetadata::PACKAGE_NAME,
            'package.version=' . $metadata->version,
            'runtime.scope=' . AboutMetadata::RUNTIME_SCOPE,
            'runtime.phpVersion=' . $metadata->phpVersion,
            'runtime.sapi=' . $metadata->sapi,
            'projectionBuildSet=' . $report->projectionBuildSet,
        ];

        return implode("\n", $lines) . "\n";
    }

    private function json(AboutReport $report): string
    {
        return $this->codec->canonicalJson($report->toArray()) . "\n";
    }
}
