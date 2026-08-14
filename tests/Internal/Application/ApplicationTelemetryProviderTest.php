<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationTelemetryProviderTest extends TestCase
{
    public function testProviderIsBoundToEachImmutableConfigurationSnapshot(): void
    {
        $first = $this->createMock(TracerProviderInterface::class);
        $second = $this->createMock(TracerProviderInterface::class);
        $firstSnapshot = new ApplicationConfigurationSnapshot('', [], [], [], [], $first);
        $secondSnapshot = new ApplicationConfigurationSnapshot('', [], [], [], [], $second);

        self::assertSame($first, $firstSnapshot->tracerProvider());
        self::assertSame($second, $secondSnapshot->tracerProvider());
        self::assertNotSame($firstSnapshot->tracerProvider(), $secondSnapshot->tracerProvider());
    }

    public function testMeterProviderIsBoundToEachImmutableConfigurationSnapshot(): void
    {
        $first = $this->createMock(MeterProviderInterface::class);
        $second = $this->createMock(MeterProviderInterface::class);
        $firstSnapshot = new ApplicationConfigurationSnapshot('', [], [], [], [], null, $first);
        $secondSnapshot = new ApplicationConfigurationSnapshot('', [], [], [], [], null, $second);

        self::assertSame($first, $firstSnapshot->meterProvider());
        self::assertSame($second, $secondSnapshot->meterProvider());
        self::assertNotSame($firstSnapshot->meterProvider(), $secondSnapshot->meterProvider());
    }
}
