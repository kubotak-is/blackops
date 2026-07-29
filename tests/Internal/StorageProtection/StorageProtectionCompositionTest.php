<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\StorageProtection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use PHPUnit\Framework\TestCase;

final class StorageProtectionCompositionTest extends TestCase
{
    public function testCompiledBindingUsesTheSameApplicationProviderContract(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $provider = new CompositionKeyProvider();
        $compiler->apply($builder, [new CompositionServiceProvider($provider)]);
        self::assertTrue($builder->has(StorageKeyProvider::class));
        $compiler->registerStorageProtection($builder);
        self::assertTrue($builder->hasDefinition(BopdEnvelopeCodec::class));
        $container = $compiler->compile($builder);

        self::assertSame($provider, $container->get(StorageKeyProvider::class));
        self::assertInstanceOf(BopdEnvelopeCodec::class, $container->get(BopdEnvelopeCodec::class));
    }

    public function testNoProviderDoesNotCreateASecretBearingArtifact(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerStorageProtection($builder);

        self::assertFalse($builder->has(BopdEnvelopeCodec::class));
    }

    public function testCompiledArtifactContainsNoResolvedKeyMaterial(): void
    {
        CountingKeyProvider::$constructs = 0;
        CountingKeyProvider::$calls = 0;
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new CountingServiceProvider()]);
        $compiler->registerStorageProtection($builder);
        $compiler->compile($builder);
        $directory = sys_get_temp_dir() . '/blackops-storage-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/container.php';
        new RuntimeContainerDumper()->dump($builder, $path, 'StorageContainer', __NAMESPACE__ . '\\Generated');

        $source = (string) file_get_contents($path);
        self::assertStringNotContainsString('01234567890123456789012345678901', $source);
        self::assertStringNotContainsString('composition-secret-marker', $source);
        self::assertSame(0, CountingKeyProvider::$constructs);
        self::assertSame(0, CountingKeyProvider::$calls);
    }
}

final readonly class CompositionServiceProvider implements ServiceProvider
{
    public function __construct(
        private StorageKeyProvider $provider,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(StorageKeyProvider::class, $this->provider);
    }
}

final readonly class CountingServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(StorageKeyProvider::class, CountingKeyProvider::class);
    }
}

final class CountingKeyProvider implements StorageKeyProvider
{
    public static int $constructs = 0;
    public static int $calls = 0;

    public function __construct()
    {
        self::$constructs++;
    }

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        self::$calls++;

        return new StorageKey('counting:v1', str_repeat('q', 32));
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        self::$calls++;

        return new StorageKey($keyId, str_repeat('q', 32));
    }
}

final readonly class CompositionKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('composition:v1', str_pad('composition-secret-marker', 32, 'x'));
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_pad('composition-secret-marker', 32, 'x'));
    }
}
