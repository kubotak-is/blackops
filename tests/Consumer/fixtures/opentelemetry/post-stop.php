<?php

declare(strict_types=1);

require '/framework/vendor/autoload.php';

use BlackOps\Internal\Telemetry\TelemetryTracer;
use OpenTelemetry\API\LoggerHolder;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

LoggerHolder::disable();
$transport = new OtlpHttpTransportFactory()->create(
    'http://collector:4318/v1/traces',
    ContentTypes::PROTOBUF,
    timeout: 0.2,
    maxRetries: 0,
);
$provider = new TracerProvider(new SimpleSpanProcessor(new SpanExporter($transport)));
$telemetry = new TelemetryTracer($provider);
$span = $telemetry->start('blackops.post_stop_probe');
$span->end();

echo "post-stop telemetry call completed\n";
