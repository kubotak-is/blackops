<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use Psr\Container\ContainerInterface;

final class ApplicationStorageProtectionResolver
{
    public static function resolve(ContainerInterface $container, ?TelemetryMetrics $metrics = null): BopdEnvelopeCodec
    {
        if ($container->has(BopdEnvelopeCodec::class)) {
            $codec = $container->get(BopdEnvelopeCodec::class);
            if ($codec instanceof BopdEnvelopeCodec) {
                return $metrics === null ? $codec : $codec->withMetrics($metrics);
            }
        }
        if (!$container->has(\BlackOps\StorageProtection\StorageKeyProvider::class)) {
            throw new \LogicException('Storage protection provider is required for application bootstrap.');
        }
        $provider = $container->get(\BlackOps\StorageProtection\StorageKeyProvider::class);
        if (!$provider instanceof \BlackOps\StorageProtection\StorageKeyProvider) {
            throw new \LogicException('Storage protection provider is invalid.');
        }
        return new BopdEnvelopeCodec($provider, metrics: $metrics);
    }
}
