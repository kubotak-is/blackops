<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Internal\Console\CompileRuntimeContainerCommand;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CompileRuntimeContainerCommandTest extends TestCase
{
    public function testCompilesAndDumpsRuntimeContainerFromProviderConfig(): void
    {
        $config = $this->configPath();
        $output = $this->containerPath();
        $class = 'RuntimeContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        file_put_contents($config, '<?php return [\\' . CommandConfigProvider::class . '::class];');

        $status = new CommandTester(new CompileRuntimeContainerCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--class' => $class,
            '--namespace' => $namespace,
        ]);

        self::assertSame(0, $status);
        self::assertFileExists($output);
        require_once $output;

        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(CommandConfiguredService::class, $container->get(CommandConfiguredService::class));
    }

    public function testRejectsMissingProviderConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileRuntimeContainerCommand())->execute([
            'config' => $this->configPath(),
            'output' => $this->containerPath(),
        ]);
    }

    private function configPath(): string
    {
        return sys_get_temp_dir() . '/blackops-runtime-container-config-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function containerPath(): string
    {
        return sys_get_temp_dir() . '/blackops-runtime-container-command-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class CommandConfigProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(CommandConfiguredService::class);
    }
}

final readonly class CommandConfiguredService {}
