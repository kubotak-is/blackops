<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationStorageProtectionResolver;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\Tests\Internal\Telemetry\RecordingMeterProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ApplicationStorageProtectionResolverTest extends TestCase
{
    public function testMissingProviderAndCodecFailsBeforeStorageUseWithoutMaterial(): void
    {
        try {
            ApplicationStorageProtectionResolver::resolve(new ResolverTestContainer([]));
            self::fail('Expected protected storage bootstrap failure.');
        } catch (\LogicException $exception) {
            self::assertSame(
                'Storage protection provider is required for application bootstrap.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('key', strtolower($exception->getMessage()));
        }
    }

    public function testRegisteredCodecIsReusedWithoutResolvingProvider(): void
    {
        $codec = new BopdEnvelopeCodec($this->createStub(StorageKeyProvider::class));

        self::assertSame($codec, ApplicationStorageProtectionResolver::resolve(new ResolverTestContainer([
            BopdEnvelopeCodec::class => $codec,
        ])));
    }

    public function testRegisteredCodecCanBeReusedWithApplicationMetrics(): void
    {
        $codec = new BopdEnvelopeCodec($this->createStub(StorageKeyProvider::class));
        $resolved = ApplicationStorageProtectionResolver::resolve(new ResolverTestContainer([
            BopdEnvelopeCodec::class => $codec,
        ]), new TelemetryMetrics(new RecordingMeterProvider()));

        self::assertNotSame($codec, $resolved);
    }
}

final readonly class ResolverTestContainer implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(
        private array $services,
    ) {}

    public function get(string $id): object
    {
        if (!isset($this->services[$id])) {
            throw new class extends \RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
