<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Telemetry;

use OpenTelemetry\API\Metrics\AsynchronousInstrument;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopObservableCallback;
use OpenTelemetry\API\Metrics\Noop\NoopObservableCounter;
use OpenTelemetry\API\Metrics\Noop\NoopObservableGauge;
use OpenTelemetry\API\Metrics\Noop\NoopObservableUpDownCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObservableCounterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\Context\ContextInterface;

final class RecordingMeterProvider implements MeterProviderInterface
{
    /** @var list<RecordingMetricInstrument> */
    public array $instruments = [];

    public ?string $scopeName = null;

    public ?string $scopeVersion = null;

    public bool $failFirstUpDownAdd = false;

    public function getMeter(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): MeterInterface {
        $this->scopeName = $name;
        $this->scopeVersion = $version;
        return new RecordingMeter($this);
    }
}

final class RecordingMeter implements MeterInterface
{
    public function __construct(
        private RecordingMeterProvider $provider,
    ) {}

    public function batchObserve(
        callable $callback,
        AsynchronousInstrument $instrument,
        AsynchronousInstrument ...$instruments,
    ): ObservableCallbackInterface {
        return new NoopObservableCallback();
    }

    public function createCounter(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $advisory = [],
    ): CounterInterface {
        return $this->add($name, $unit, 'counter');
    }

    public function createHistogram(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $advisory = [],
    ): HistogramInterface {
        return $this->add($name, $unit, 'histogram');
    }

    public function createUpDownCounter(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $advisory = [],
    ): UpDownCounterInterface {
        $instrument = $this->add($name, $unit, 'updowncounter');
        $instrument->failFirstAdd = $this->provider->failFirstUpDownAdd;
        return $instrument;
    }

    public function createGauge(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $advisory = [],
    ): GaugeInterface {
        return new \OpenTelemetry\API\Metrics\Noop\NoopGauge();
    }

    public function createObservableCounter(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array|callable $advisory = [],
        callable ...$callbacks,
    ): ObservableCounterInterface {
        return new NoopObservableCounter();
    }

    public function createObservableGauge(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array|callable $advisory = [],
        callable ...$callbacks,
    ): ObservableGaugeInterface {
        return new NoopObservableGauge();
    }

    public function createObservableUpDownCounter(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array|callable $advisory = [],
        callable ...$callbacks,
    ): ObservableUpDownCounterInterface {
        return new NoopObservableUpDownCounter();
    }

    private function add(string $name, ?string $unit, string $type): RecordingMetricInstrument
    {
        $instrument = new RecordingMetricInstrument($name, $unit, $type);
        $this->provider->instruments[] = $instrument;
        return $instrument;
    }
}

final class RecordingMetricInstrument implements CounterInterface, HistogramInterface, UpDownCounterInterface
{
    /** @var list<array{amount: float|int, attributes: array<string, mixed>}> */
    public array $records = [];

    public function __construct(
        public readonly string $name,
        public readonly ?string $unit,
        public readonly string $type,
    ) {}

    public bool $failFirstAdd = false;

    public function isEnabled(): bool
    {
        return true;
    }

    public function add($amount, iterable $attributes = [], $context = null): void
    {
        if ($this->failFirstAdd) {
            $this->failFirstAdd = false;
            throw new \RuntimeException('instrument failure');
        }
        $this->records[] = [
            'amount' => $amount,
            'attributes' => is_array($attributes) ? $attributes : iterator_to_array($attributes),
        ];
    }

    public function record(
        float|int $amount,
        iterable $attributes = [],
        ContextInterface|false|null $context = null,
    ): void {
        $this->add($amount, $attributes, $context);
    }
}
