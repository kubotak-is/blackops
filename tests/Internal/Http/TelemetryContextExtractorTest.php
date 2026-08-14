<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Http;

use BlackOps\Internal\Http\TelemetryContextExtractor;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class TelemetryContextExtractorTest extends TestCase
{
    public function testValidCarrierIsExtracted(): void
    {
        $context = new TelemetryContextExtractor()->extract(new ServerRequest('GET', '/', [
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
            'tracestate' => 'vendor=value',
        ]));
        self::assertSame('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', $context?->traceparent());
    }

    public function testMalformedOrMultipleCarrierIsRootSafeWithoutRawValue(): void
    {
        $extractor = new TelemetryContextExtractor();
        self::assertNull($extractor->extract(new ServerRequest('GET', '/', [
            'traceparent' => "00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01\n",
        ])));
        self::assertNull($extractor->extract(new ServerRequest('GET', '/', [
            'traceparent' => [
                '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
                '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00',
            ],
        ])));
    }
}
