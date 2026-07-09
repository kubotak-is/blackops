<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Internal\Discovery\InstalledComposerProviderDiscovery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InstalledComposerProviderDiscoveryTest extends TestCase
{
    public function testDiscoversProvidersFromComposerTwoInstalledMetadata(): void
    {
        $path = $this->installedPath();
        $this->writeJson($path, [
            'packages' => [
                ['name' => 'vendor/empty'],
                [
                    'name' => 'vendor/with-providers',
                    'extra' => [
                        'blackops' => [
                            'operation-providers' => [InstalledOperationProvider::class],
                            'service-providers' => [InstalledServiceProvider::class],
                        ],
                    ],
                ],
            ],
        ]);

        $providers = new InstalledComposerProviderDiscovery()->discover($path);

        self::assertSame([InstalledOperationProvider::class], $providers->operationProviders);
        self::assertSame([InstalledServiceProvider::class], $providers->serviceProviders);
    }

    public function testDiscoversProvidersFromLegacyPackageList(): void
    {
        $path = $this->installedPath();
        $this->writeJson($path, [
            [
                'name' => 'vendor/legacy',
                'extra' => [
                    'blackops' => [
                        'operation-providers' => [InstalledOperationProvider::class],
                    ],
                ],
            ],
        ]);

        $providers = new InstalledComposerProviderDiscovery()->discover($path);

        self::assertSame([InstalledOperationProvider::class], $providers->operationProviders);
        self::assertSame([], $providers->serviceProviders);
    }

    public function testRejectsInvalidInstalledJson(): void
    {
        $path = $this->installedPath();
        file_put_contents($path, '{invalid');

        $this->expectException(InvalidArgumentException::class);

        new InstalledComposerProviderDiscovery()->discover($path);
    }

    public function testRejectsInvalidInstalledMetadataShape(): void
    {
        $path = $this->installedPath();
        $this->writeJson($path, ['name' => 'vendor/root']);

        $this->expectException(InvalidArgumentException::class);

        new InstalledComposerProviderDiscovery()->discover($path);
    }

    public function testRejectsInvalidPackageEntry(): void
    {
        $path = $this->installedPath();
        $this->writeJson($path, ['packages' => ['invalid']]);

        $this->expectException(InvalidArgumentException::class);

        new InstalledComposerProviderDiscovery()->discover($path);
    }

    public function testRejectsInvalidProviderEntry(): void
    {
        $path = $this->installedPath();
        $this->writeJson($path, [
            'packages' => [
                [
                    'extra' => [
                        'blackops' => [
                            'operation-providers' => [123],
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new InstalledComposerProviderDiscovery()->discover($path);
    }

    private function installedPath(): string
    {
        return sys_get_temp_dir() . '/blackops-installed-composer-provider-' . bin2hex(random_bytes(8)) . '.json';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    }
}

final readonly class InstalledOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [InstalledOperation::class];
    }
}

final readonly class InstalledServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void {}
}

final readonly class InstalledOperation implements Operation {}
