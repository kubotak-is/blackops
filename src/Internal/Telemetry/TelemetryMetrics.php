<?php

declare(strict_types=1);

namespace BlackOps\Internal\Telemetry;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:no-empty-catch-clause
 * @mago-expect lint:cyclomatic-complexity
 */
final class TelemetryMetrics
{
    public const SCOPE = 'blackops.framework';
    public const VERSION = '1.1.0';

    /** @var list<string> */
    public const RESULTS = ['completed', 'rejected', 'failed', 'retry_scheduled', 'dead_lettered', 'interrupted'];

    /** @var list<string> */
    public const RUNTIME_KINDS = ['operation', 'worker', 'scheduler', 'maintenance', 'outbox_relay', 'observer_replay'];

    /** @var list<string> */
    public const SCHEDULER_KINDS = ['application', 'maintenance'];

    /** @var list<string> */
    public const OBSERVER_KINDS = ['aggregator', 'replay'];

    /** @var list<string> */
    public const STRATEGIES = ['inline', 'deferred'];

    /** @var list<string> */
    public const FAILURE_CODES = [
        'claim_lost',
        'observe_failed',
        'flush_failed',
        'replay_failed',
        'encryption_failed',
        'decryption_failed',
        'header_parse_failed',
        'unknown',
    ];

    /** @var list<string> */
    public const STORAGE_PURPOSES = [
        'journal_record',
        'deferred_payload',
        'deferred_context',
        'outcome_payload',
        'outbox_payload',
        'outbox_context',
        'dead_letter_reason',
        'idempotency_response',
        'idempotency_result',
        'unknown',
    ];

    /** @var array<string, array{type: string, unit: string}> */
    public const INSTRUMENTS = [
        'blackops.operation.duration' => ['type' => 'histogram', 'unit' => 's'],
        'blackops.operation.active' => ['type' => 'updowncounter', 'unit' => '{operation}'],
        'blackops.worker.claims' => ['type' => 'counter', 'unit' => '{claim}'],
        'blackops.worker.heartbeat.failures' => ['type' => 'counter', 'unit' => '{failure}'],
        'blackops.outbox.relay.duration' => ['type' => 'histogram', 'unit' => 's'],
        'blackops.outbox.relay.records' => ['type' => 'counter', 'unit' => '{record}'],
        'blackops.scheduler.run.duration' => ['type' => 'histogram', 'unit' => 's'],
        'blackops.scheduler.occurrences' => ['type' => 'counter', 'unit' => '{occurrence}'],
        'blackops.observer.failures' => ['type' => 'counter', 'unit' => '{failure}'],
        'blackops.storage.protection.failures' => ['type' => 'counter', 'unit' => '{failure}'],
    ];

    private MeterInterface $meter;
    private HistogramInterface $operationDuration;
    private UpDownCounterInterface $operationActive;
    private CounterInterface $workerClaims;
    private CounterInterface $heartbeatFailures;
    private HistogramInterface $relayDuration;
    private CounterInterface $relayRecords;
    private HistogramInterface $schedulerDuration;
    private CounterInterface $schedulerOccurrences;
    private CounterInterface $observerFailures;
    private CounterInterface $protectionFailures;
    /** @var array<string, true> */
    private array $operationTypes;

    /** @param iterable<string> $compiledOperationTypes */
    public function __construct(?MeterProviderInterface $provider = null, iterable $compiledOperationTypes = [])
    {
        $this->operationTypes = [];
        foreach ($compiledOperationTypes as $operationType) {
            if ($operationType === '') {
                continue;
            }
            $this->operationTypes[$operationType] = true;
        }
        try {
            $this->meter = ($provider ?? new NoopMeterProvider())->getMeter(self::SCOPE, self::VERSION);
        } catch (\Throwable) {
            $this->meter = new NoopMeterProvider()->getMeter(self::SCOPE, self::VERSION);
        }
        $noop = new NoopMeterProvider()->getMeter(self::SCOPE, self::VERSION);
        $this->operationDuration = $this->createHistogram('blackops.operation.duration', 's', $noop);
        $this->operationActive = $this->createUpDown('blackops.operation.active', '{operation}', $noop);
        $this->workerClaims = $this->createCounter('blackops.worker.claims', '{claim}', $noop);
        $this->heartbeatFailures = $this->createCounter('blackops.worker.heartbeat.failures', '{failure}', $noop);
        $this->relayDuration = $this->createHistogram('blackops.outbox.relay.duration', 's', $noop);
        $this->relayRecords = $this->createCounter('blackops.outbox.relay.records', '{record}', $noop);
        $this->schedulerDuration = $this->createHistogram('blackops.scheduler.run.duration', 's', $noop);
        $this->schedulerOccurrences = $this->createCounter('blackops.scheduler.occurrences', '{occurrence}', $noop);
        $this->observerFailures = $this->createCounter('blackops.observer.failures', '{failure}', $noop);
        $this->protectionFailures = $this->createCounter('blackops.storage.protection.failures', '{failure}', $noop);
    }

    /** @param array<string, mixed> $attributes @return array<non-empty-string, string> */
    public function operation(array $attributes = []): TelemetryMetricScope
    {
        $safe = $this->operationAttributes($attributes);
        /** @var array<non-empty-string, string> $safe */

        return new TelemetryMetricScope($this->operationDuration, $this->operationActive, $safe);
    }

    public function relayScope(): TelemetryMetricTimer
    {
        return new TelemetryMetricTimer($this->relayDuration, []);
    }

    public function schedulerScope(string $kind): TelemetryMetricTimer
    {
        return new TelemetryMetricTimer($this->schedulerDuration, [
            'blackops.scheduler.kind' => $this->schedulerKind($kind),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function workerClaim(string $result, array $attributes = []): void
    {
        $this->add($this->workerClaims, 1, ['blackops.result' => $this->resultValue($result)]);
    }

    public function heartbeatFailure(string $code): void
    {
        $this->add($this->heartbeatFailures, 1, ['blackops.failure.code' => $this->failureCode($code)]);
    }

    public function relayRecord(string $result): void
    {
        $this->add($this->relayRecords, 1, ['blackops.result' => $this->resultValue($result)]);
    }

    public function schedulerOccurrence(string $result, string $kind = 'application'): void
    {
        $this->add($this->schedulerOccurrences, 1, [
            'blackops.scheduler.kind' => $this->schedulerKind($kind),
            'blackops.result' => $this->resultValue($result),
        ]);
    }

    public function observerFailure(string $kind, string $code): void
    {
        $this->add($this->observerFailures, 1, [
            'blackops.observer.kind' => $this->observerKind($kind),
            'blackops.failure.code' => $this->failureCode($code),
        ]);
    }

    public function protectionFailure(string $purpose, string $code): void
    {
        $this->add($this->protectionFailures, 1, [
            'blackops.storage.purpose' => $this->storagePurpose($purpose),
            'blackops.failure.code' => $this->failureCode($code),
        ]);
    }

    /** @param array<string, mixed> $attributes @return array<non-empty-string, string> */
    private function operationAttributes(array $attributes): array
    {
        $safe = [];
        foreach (['blackops.operation.type', 'blackops.operation.strategy', 'blackops.runtime.kind'] as $key) {
            if (!array_key_exists($key, $attributes) || !is_string($attributes[$key])) {
                continue;
            }
            if (
                $key === 'blackops.operation.type'
                && ($this->operationTypes === [] || !array_key_exists($attributes[$key], $this->operationTypes))
            ) {
                continue;
            }
            $safe[$key] = $this->attributeValue($key, $attributes[$key]);
        }

        /** @var array<non-empty-string, string> $safe */
        return $safe;
    }

    private function createHistogram(string $name, string $unit, MeterInterface $noop): HistogramInterface
    {
        try {
            return $this->meter->createHistogram($name, $unit);
        } catch (\Throwable) {
            return $noop->createHistogram($name, $unit);
        }
    }

    private function createCounter(string $name, string $unit, MeterInterface $noop): CounterInterface
    {
        try {
            return $this->meter->createCounter($name, $unit);
        } catch (\Throwable) {
            return $noop->createCounter($name, $unit);
        }
    }

    private function createUpDown(string $name, string $unit, MeterInterface $noop): UpDownCounterInterface
    {
        try {
            return $this->meter->createUpDownCounter($name, $unit);
        } catch (\Throwable) {
            return $noop->createUpDownCounter($name, $unit);
        }
    }

    private function attributeValue(string $key, string $value): string
    {
        return match ($key) {
            'blackops.result' => $this->resultValue($value),
            'blackops.runtime.kind' => $this->runtimeKind($value),
            'blackops.operation.strategy' => $this->strategy($value),
            'blackops.scheduler.kind' => $this->schedulerKind($value),
            'blackops.failure.code' => $this->failureCode($value),
            default => $value,
        };
    }

    private function resultValue(string $value): string
    {
        return in_array($value, self::RESULTS, strict: true) ? $value : 'failed';
    }

    private function runtimeKind(string $value): string
    {
        return in_array($value, self::RUNTIME_KINDS, strict: true) ? $value : 'operation';
    }

    private function strategy(string $value): string
    {
        return in_array($value, self::STRATEGIES, strict: true) ? $value : 'inline';
    }

    private function schedulerKind(string $value): string
    {
        return in_array($value, self::SCHEDULER_KINDS, strict: true) ? $value : 'application';
    }

    private function failureCode(string $value): string
    {
        return in_array($value, self::FAILURE_CODES, strict: true) ? $value : 'unknown';
    }

    private function observerKind(string $value): string
    {
        return in_array($value, self::OBSERVER_KINDS, strict: true) ? $value : 'replay';
    }

    private function storagePurpose(string $value): string
    {
        return in_array($value, self::STORAGE_PURPOSES, strict: true) ? $value : 'unknown';
    }

    /** @param array<non-empty-string, string> $attributes */
    private function add(CounterInterface $instrument, int $amount, array $attributes): void
    {
        try {
            $instrument->add($amount, $attributes);
        } catch (\Throwable) {
        }
    }
}
