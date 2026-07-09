<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Internal\Discovery\ComposerProviderDiscovery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ComposerProviderDiscoveryTest extends TestCase
{
    public function testDiscoversOperationAndServiceProvidersFromComposerMetadata(): void
    {
        $path = $this->composerPath();
        $this->writeJson($path, [
            'extra' => [
                'blackops' => [
                    'operation-providers' => [ComposerOperationProvider::class],
                    'service-providers' => [ComposerServiceProvider::class],
                ],
            ],
        ]);

        $providers = new ComposerProviderDiscovery()->discover($path);

        self::assertSame([ComposerOperationProvider::class], $providers->operationProviders);
        self::assertSame([ComposerServiceProvider::class], $providers->serviceProviders);
    }

    public function testMissingProviderMetadataIsEmpty(): void
    {
        $path = $this->composerPath();
        $this->writeJson($path, ['name' => 'blackops/test']);

        $providers = new ComposerProviderDiscovery()->discover($path);

        self::assertSame([], $providers->operationProviders);
        self::assertSame([], $providers->serviceProviders);
    }

    public function testRejectsInvalidComposerJson(): void
    {
        $path = $this->composerPath();
        file_put_contents($path, '{invalid');

        $this->expectException(InvalidArgumentException::class);

        new ComposerProviderDiscovery()->discover($path);
    }

    public function testRejectsInvalidProviderEntry(): void
    {
        $path = $this->composerPath();
        $this->writeJson($path, [
            'extra' => [
                'blackops' => [
                    'operation-providers' => [123],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new ComposerProviderDiscovery()->discover($path);
    }

    public function testRejectsProviderThatDoesNotImplementContract(): void
    {
        $path = $this->composerPath();
        $this->writeJson($path, [
            'extra' => [
                'blackops' => [
                    'service-providers' => [ComposerOperationProvider::class],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new ComposerProviderDiscovery()->discover($path);
    }

    private function composerPath(): string
    {
        return sys_get_temp_dir() . '/blackops-composer-provider-' . bin2hex(random_bytes(8)) . '.json';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    }
}

final readonly class ComposerOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ComposerOperation::class];
    }
}

final readonly class ComposerServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void {}
}

final readonly class ComposerOperation implements Operation {}
