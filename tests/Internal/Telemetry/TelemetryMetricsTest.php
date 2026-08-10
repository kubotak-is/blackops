<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Telemetry;

use BlackOps\Internal\Telemetry\TelemetryMetrics;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use PHPUnit\Framework\TestCase;

final class TelemetryMetricsTest extends TestCase
{
    public function testCreatesStableInstrumentMatrixAndBalancesActiveCounter(): void
    {
        self::assertSame('1.2.0', TelemetryMetrics::VERSION);
        $provider = new RecordingMeterProvider();
        $metrics = new TelemetryMetrics($provider, ['order.create']);
        self::assertSame(TelemetryMetrics::SCOPE, $provider->scopeName);
        self::assertSame(TelemetryMetrics::VERSION, $provider->scopeVersion);
        $scope = $metrics->operation([
            'blackops.operation.type' => 'order.create',
            'blackops.operation.strategy' => 'deferred',
            'blackops.runtime.kind' => 'worker',
            'blackops.operation.id' => 'must-not-be-a-label',
        ]);
        $scope->result('completed');
        $scope->end();

        self::assertSame(array_keys(TelemetryMetrics::INSTRUMENTS), array_column($provider->instruments, 'name'));
        self::assertSame(
            [
                'histogram',
                'updowncounter',
                'counter',
                'counter',
                'histogram',
                'counter',
                'histogram',
                'counter',
                'counter',
                'counter',
            ],
            array_column($provider->instruments, 'type'),
        );
        self::assertSame(
            [
                's',
                '{operation}',
                '{claim}',
                '{failure}',
                's',
                '{record}',
                's',
                '{occurrence}',
                '{failure}',
                '{failure}',
            ],
            array_column($provider->instruments, 'unit'),
        );
        $active = $provider->instruments[1];
        self::assertSame(1, $active->records[0]['amount']);
        self::assertSame(-1, $active->records[1]['amount']);
        self::assertSame('worker', $active->records[0]['attributes']['blackops.runtime.kind']);
        self::assertSame('deferred', $active->records[0]['attributes']['blackops.operation.strategy']);
        self::assertArrayNotHasKey('blackops.operation.id', $active->records[0]['attributes']);
        $forbiddenProvider = new RecordingMeterProvider();
        $forbidden = new TelemetryMetrics($forbiddenProvider, ['order.create'])->operation([
            'blackops.operation.type' => 'free.form.type',
            'blackops.operation.strategy' => 'inline',
            'blackops.runtime.kind' => 'operation',
        ]);
        $forbidden->end();
        self::assertArrayNotHasKey(
            'blackops.operation.type',
            $forbiddenProvider->instruments[1]->records[0]['attributes'],
        );
        self::assertSame('completed', $provider->instruments[0]->records[0]['attributes']['blackops.result']);
    }

    public function testThrowingProviderFallsBackToNoopWithoutChangingLifecycle(): void
    {
        $provider = $this->createMock(MeterProviderInterface::class);
        $provider->method('getMeter')->willThrowException(new \RuntimeException('provider detail'));
        $scope = new TelemetryMetrics($provider)->operation(['blackops.result' => 'rejected']);
        $scope->fail();
        $scope->end();
        self::addToAssertionCount(1);
    }

    public function testOperationResultMatrixHasExactAttributesAndBalancesActive(): void
    {
        foreach (TelemetryMetrics::RESULTS as $result) {
            $provider = new RecordingMeterProvider();
            $metrics = new TelemetryMetrics($provider, ['order.create']);
            $scope = $metrics->operation([
                'blackops.operation.type' => 'order.create',
                'blackops.operation.strategy' => 'inline',
                'blackops.runtime.kind' => 'operation',
                'blackops.operation.id' => 'forbidden',
            ]);
            $scope->result($result);
            $scope->end();

            $duration = $provider->instruments[0]->records[0]['attributes'];
            $active = $provider->instruments[1]->records;
            self::assertSame(
                [
                    'blackops.operation.type',
                    'blackops.operation.strategy',
                    'blackops.runtime.kind',
                    'blackops.result',
                ],
                array_keys($duration),
            );
            self::assertSame(
                [
                    'blackops.operation.type',
                    'blackops.operation.strategy',
                    'blackops.runtime.kind',
                ],
                array_keys($active[0]['attributes']),
            );
            self::assertSame(1, $active[0]['amount']);
            self::assertSame(-1, $active[1]['amount']);
            self::assertSame($result, $duration['blackops.result']);
            self::assertArrayNotHasKey('blackops.operation.id', $duration);
        }
    }

    public function testRuntimeCountersUseFiniteEnumsAndSafeAttributes(): void
    {
        $provider = new RecordingMeterProvider();
        $metrics = new TelemetryMetrics($provider);

        $metrics->workerClaim('not-a-result', ['blackops.operation.id' => 'identity']);
        $metrics->heartbeatFailure('free-form-failure');
        $metrics->relayRecord('not-a-result');
        $relay = $metrics->relayScope();
        $relay->result('not-a-result');
        $relay->end();
        $metrics->schedulerOccurrence('completed', 'not-a-kind');
        $scheduler = $metrics->schedulerScope('not-a-kind');
        $scheduler->result('not-a-result');
        $scheduler->end();
        $metrics->observerFailure('not-a-kind', 'free-form-failure');
        $metrics->protectionFailure('not-a-purpose', 'free-form-failure');

        self::assertSame('failed', $provider->instruments[2]->records[0]['attributes']['blackops.result']);
        self::assertArrayNotHasKey('blackops.operation.id', $provider->instruments[2]->records[0]['attributes']);
        self::assertSame('unknown', $provider->instruments[3]->records[0]['attributes']['blackops.failure.code']);
        self::assertSame('failed', $provider->instruments[5]->records[0]['attributes']['blackops.result']);
        self::assertSame('application', $provider->instruments[7]->records[0]['attributes']['blackops.scheduler.kind']);
        self::assertSame('replay', $provider->instruments[8]->records[0]['attributes']['blackops.observer.kind']);
        self::assertSame('unknown', $provider->instruments[9]->records[0]['attributes']['blackops.storage.purpose']);
        self::assertSame(['blackops.result'], array_keys($provider->instruments[2]->records[0]['attributes']));
        self::assertSame(['blackops.failure.code'], array_keys($provider->instruments[3]->records[0]['attributes']));
        self::assertSame(['blackops.result'], array_keys($provider->instruments[4]->records[0]['attributes']));
        self::assertSame(['blackops.result'], array_keys($provider->instruments[5]->records[0]['attributes']));
        self::assertSame(
            ['blackops.scheduler.kind', 'blackops.result'],
            array_keys($provider->instruments[6]->records[0]['attributes']),
        );
        self::assertSame(
            ['blackops.scheduler.kind', 'blackops.result'],
            array_keys($provider->instruments[7]->records[0]['attributes']),
        );
        self::assertSame(
            ['blackops.observer.kind', 'blackops.failure.code'],
            array_keys($provider->instruments[8]->records[0]['attributes']),
        );
        self::assertSame(
            ['blackops.storage.purpose', 'blackops.failure.code'],
            array_keys($provider->instruments[9]->records[0]['attributes']),
        );
    }

    public function testActiveCounterDoesNotDecrementAfterInitialIncrementFailure(): void
    {
        $provider = new RecordingMeterProvider();
        $provider->failFirstUpDownAdd = true;
        $scope = new TelemetryMetrics($provider, ['order.create'])->operation([
            'blackops.operation.type' => 'order.create',
            'blackops.operation.strategy' => 'inline',
            'blackops.runtime.kind' => 'operation',
        ]);
        $scope->end();

        self::assertCount(0, $provider->instruments[1]->records);
    }
}
