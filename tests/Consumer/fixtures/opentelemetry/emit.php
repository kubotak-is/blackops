<?php

declare(strict_types=1);

require '/framework/vendor/autoload.php';

use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Telemetry\TelemetryContext;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$metricTemporality = getenv('BLACKOPS_OTEL_METRIC_TEMPORALITY');
if ($metricTemporality !== false && $metricTemporality !== 'cumulative') {
    fwrite(STDERR, "Unsupported metric temporality\n");
    exit(2);
}

$transportFactory = new OtlpHttpTransportFactory();
$spanExporter = new SpanExporter($transportFactory->create('http://collector:4318/v1/traces', ContentTypes::PROTOBUF));
$tracerProvider = new TracerProvider(new SimpleSpanProcessor($spanExporter));

$metricTransport = $transportFactory->create('http://collector:4318/v1/metrics', ContentTypes::PROTOBUF);
$metricExporter = $metricTemporality === 'cumulative'
    ? new MetricExporter($metricTransport, Temporality::CUMULATIVE)
    : new MetricExporter($metricTransport);
$reader = new ExportingReader($metricExporter);
$meterProvider = MeterProvider::builder()->addReader($reader)->build();
$telemetry = new TelemetryTracer($tracerProvider);
$metrics = new TelemetryMetrics($meterProvider, [
    'inline.probe',
    'deferred.probe',
    'scheduled.probe',
]);

$emitSpan = static function (
    string $name,
    int $kind,
    array $attributes = [],
    ?string $result = null,
    ?TelemetryContext $parent = null,
) use ($telemetry): array {
    $scope = $telemetry->start($name, $kind, parent: $parent, attributes: $attributes);
    $scope->result($result ?? 'completed');
    $context = $telemetry->currentContext();
    $scope->end();

    return [
        'traceId' => $context === null ? null : explode('-', $context->traceparent())[1],
        'spanId' => $context === null ? null : explode('-', $context->traceparent())[2],
        'context' => $context,
    ];
};

$serverScope = $telemetry->start('blackops.http.request', SpanKind::KIND_SERVER, attributes: [
    'blackops.runtime.kind' => 'operation',
]);
$serverContext = $telemetry->currentContext();
$inline = $emitSpan(
    'blackops.operation.execute',
    TelemetryTracer::KIND_INTERNAL,
    [
        'blackops.operation.type' => 'inline.probe',
        'blackops.operation.strategy' => 'inline',
        'blackops.runtime.kind' => 'operation',
    ],
    parent: $serverContext,
);
$serverScope->end();
$deferredProducer = $emitSpan('blackops.operation.accept', TelemetryTracer::KIND_PRODUCER, [
    'blackops.operation.type' => 'deferred.probe',
    'blackops.operation.strategy' => 'deferred',
    'blackops.runtime.kind' => 'operation',
]);
$deferredWorker = $emitSpan(
    'blackops.operation.execute',
    TelemetryTracer::KIND_CONSUMER,
    [
        'blackops.operation.type' => 'deferred.probe',
        'blackops.operation.strategy' => 'deferred',
        'blackops.runtime.kind' => 'worker',
    ],
    parent: $deferredProducer['context'],
);
$deferredRetry = $emitSpan(
    'blackops.operation.execute',
    TelemetryTracer::KIND_CONSUMER,
    [
        'blackops.operation.type' => 'deferred.probe',
        'blackops.operation.strategy' => 'deferred',
        'blackops.runtime.kind' => 'worker',
    ],
    'retry_scheduled',
    $deferredProducer['context'],
);
$outboxProducer = $emitSpan(
    'blackops.operation.accept',
    TelemetryTracer::KIND_PRODUCER,
    [
        'blackops.operation.type' => 'deferred.probe',
        'blackops.operation.strategy' => 'deferred',
        'blackops.runtime.kind' => 'operation',
    ],
    parent: $serverContext,
);
$outboxRelay = $emitSpan(
    'blackops.outbox.relay',
    TelemetryTracer::KIND_INTERNAL,
    [
        'blackops.runtime.kind' => 'outbox_relay',
    ],
    parent: $outboxProducer['context'],
);
$emitSpan('blackops.redaction.probe', TelemetryTracer::KIND_INTERNAL, [
    'blackops.tenant.id' => 'sensitive-tenant-secret',
    'blackops.actor.execution.id' => 'sensitive-actor-secret',
]);
$emitSpan('blackops.operation.schedule.evaluate', TelemetryTracer::KIND_INTERNAL, [
    'blackops.runtime.kind' => 'scheduler',
]);
$emitSpan('blackops.maintenance.run', TelemetryTracer::KIND_INTERNAL, [
    'blackops.runtime.kind' => 'maintenance',
]);

$operation = $metrics->operation([
    'blackops.operation.type' => 'inline.probe',
    'blackops.operation.strategy' => 'inline',
    'blackops.runtime.kind' => 'operation',
]);
$operation->result('completed');
$operation->end();
$retry = $metrics->operation([
    'blackops.operation.type' => 'deferred.probe',
    'blackops.operation.strategy' => 'deferred',
    'blackops.runtime.kind' => 'worker',
    'blackops.tenant.id' => 'sensitive-tenant-secret',
]);
$retry->result('retry_scheduled');
$retry->end();
$relay = $metrics->relayScope();
$metrics->relayRecord('completed');
$relay->end();
$scheduler = $metrics->schedulerScope('application');
$metrics->schedulerOccurrence('completed', 'application');
$scheduler->end();
$maintenance = $metrics->schedulerScope('maintenance');
$metrics->schedulerOccurrence('completed', 'maintenance');
$maintenance->end();
$metrics->workerClaim('retry_scheduled');
$metrics->heartbeatFailure('claim_lost');
$metrics->observerFailure('aggregator', 'observe_failed');
$metrics->protectionFailure('journal_record', 'encryption_failed');
$meterProvider->forceFlush();

$tracerProvider->shutdown();
$meterProvider->shutdown();

$logStream = tmpfile();
$logger = new MonologJsonlLoggerFactory()->create($logStream);
$logger->info('blackops telemetry correlation', [
    'telemetry' => [
        'traceId' => $inline['traceId'],
        'spanId' => $inline['spanId'],
    ],
    'context' => ['password' => 'sensitive-tenant-secret'],
]);
rewind($logStream);
echo stream_get_contents($logStream);
fclose($logStream);
echo
    json_encode([
        'event' => 'blackops.observability.summary',
        'server' => ['traceId' => $serverContext === null ? null : explode('-', $serverContext->traceparent())[1]],
        'inline' => ['traceId' => $inline['traceId'], 'spanId' => $inline['spanId']],
        'deferredProducer' => ['traceId' => $deferredProducer['traceId'], 'spanId' => $deferredProducer['spanId']],
        'deferredWorker' => ['traceId' => $deferredWorker['traceId'], 'spanId' => $deferredWorker['spanId']],
        'deferredRetry' => ['traceId' => $deferredRetry['traceId'], 'spanId' => $deferredRetry['spanId']],
        'outboxProducer' => ['traceId' => $outboxProducer['traceId'], 'spanId' => $outboxProducer['spanId']],
        'outboxRelay' => ['traceId' => $outboxRelay['traceId'], 'spanId' => $outboxRelay['spanId']],
        'metrics' => TelemetryMetrics::INSTRUMENTS,
    ], JSON_THROW_ON_ERROR),
    PHP_EOL
;
