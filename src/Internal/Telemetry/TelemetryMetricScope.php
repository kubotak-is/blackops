<?php

declare(strict_types=1);

namespace BlackOps\Internal\Telemetry;

use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;

final class TelemetryMetricScope
{
    private bool $ended = false;
    private bool $activeStarted = false;
    private string $result = 'completed';
    private int $startedAt;

    /** @param array<non-empty-string, string> $attributes */
    public function __construct(
        private readonly HistogramInterface $duration,
        private readonly UpDownCounterInterface $active,
        private readonly array $attributes,
    ) {
        $this->startedAt = (int) hrtime(true);
        $this->activeStarted = $this->active(1);
    }

    public function result(string $result): void
    {
        if (in_array($result, TelemetryMetrics::RESULTS, strict: true)) {
            $this->result = $result;
        }
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
        $duration = (hrtime(true) - $this->startedAt) / 1_000_000_000;
        $this->safe(fn() => $this->duration->record($duration, $attributes));
        if ($this->activeStarted) {
            $this->active(-1);
        }
    }

    private function safe(\Closure $callback): bool
    {
        try {
            $callback();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function active(int $amount): bool
    {
        /** @var iterable<non-empty-string, string> $attributes */
        $attributes = $this->attributes;
        return $this->safe(function () use ($amount, $attributes): bool {
            $this->active->add($amount, $attributes);
            return true;
        });
    }
}
