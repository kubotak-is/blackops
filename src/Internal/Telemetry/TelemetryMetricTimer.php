<?php

declare(strict_types=1);

namespace BlackOps\Internal\Telemetry;

use OpenTelemetry\API\Metrics\HistogramInterface;

/** @mago-expect lint:no-empty-catch-clause */
final class TelemetryMetricTimer
{
    private bool $ended = false;
    private string $result = 'completed';
    private int $startedAt;

    /** @param array<non-empty-string, string> $attributes */
    public function __construct(
        private readonly HistogramInterface $duration,
        private readonly array $attributes,
    ) {
        $this->startedAt = (int) hrtime(true);
    }

    public function result(string $result): void
    {
        $this->result = in_array($result, TelemetryMetrics::RESULTS, strict: true) ? $result : 'failed';
    }

    public function fail(): void
    {
        $this->result('failed');
    }

    public function end(): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;
        $attributes = [...$this->attributes, 'blackops.result' => $this->result];
        try {
            $this->duration->record((hrtime(true) - $this->startedAt) / 1_000_000_000, $attributes);
        } catch (\Throwable) {
        }
    }
}
