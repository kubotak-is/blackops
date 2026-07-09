<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceProviderConfigLoaderTest extends TestCase
{
    public function testLoadsProviderInstancesFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [new \\' . InstanceConfigProvider::class . '()];');

        $providers = new ServiceProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(InstanceConfigProvider::class, $providers[0]);
    }

    public function testLoadsProviderClassNamesFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . ClassNameConfigProvider::class . '::class];');

        $providers = new ServiceProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(ClassNameConfigProvider::class, $providers[0]);
    }

    public function testLoadsSingleProviderFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return new \\' . InstanceConfigProvider::class . '();');

        $providers = new ServiceProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(InstanceConfigProvider::class, $providers[0]);
    }

    public function testLoadedProvidersCanBeAppliedToRuntimeContainerCompiler(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . ClassNameConfigProvider::class . '::class];');
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();

        $compiler->apply($builder, new ServiceProviderConfigLoader()->load($path));
        $container = $compiler->compile($builder);

        self::assertInstanceOf(ConfiguredService::class, $container->get(ConfiguredService::class));
    }

    public function testRejectsMissingConfigFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ServiceProviderConfigLoader()->load($this->configPath());
    }

    public function testRejectsInvalidReturnValue(): void
    {
        $path = $this->configPath();
        file_put_contents($path, "<?php return 'invalid';");

        $this->expectException(InvalidArgumentException::class);

        new ServiceProviderConfigLoader()->load($path);
    }

    public function testRejectsInvalidProviderEntry(): void
    {
        $path = $this->configPath();
        file_put_contents($path, "<?php return [new \\stdClass()];");

        $this->expectException(InvalidArgumentException::class);

        new ServiceProviderConfigLoader()->load($path);
    }

    public function testRejectsProviderClassWithRequiredConstructorArguments(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . ProviderWithRequiredArgument::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new ServiceProviderConfigLoader()->load($path);
    }

    private function configPath(): string
    {
        return sys_get_temp_dir() . '/blackops-service-provider-config-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class InstanceConfigProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->set('instance.configured.service', new ConfiguredService());
    }
}

final readonly class ClassNameConfigProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ConfiguredService::class);
    }
}

final readonly class ProviderWithRequiredArgument implements ServiceProvider
{
    public function __construct(
        private string $value,
    ) {}

    public function register(ServiceRegistry $services): void {}
}

final readonly class ConfiguredService {}
