<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationConsoleKernel;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\FixtureDatabaseSeeder;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\SeederFixtureDependency;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\SeederFixtureState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/Fixture/SeederDiscovery/FixtureSeeders.php';

final class ApplicationSeederConsoleIntegrationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testBuiltInCommandRunsFreshCompiledRootExactlyOnce(): void
    {
        SeederFixtureState::reset();
        $build = $this->directory();
        $snapshot = $this->snapshot(
            $build,
            'SeederConsoleContainer' . bin2hex(random_bytes(8)),
            'seeder-console-fresh',
            [
                'root' => FixtureDatabaseSeeder::class,
                'discovery' => [__DIR__ . '/Fixture/SeederDiscovery'],
            ],
            [SeederConsoleFixtureServiceProvider::class],
        );

        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($snapshot))->execute([]));
        self::assertSame(0, SeederFixtureState::$rootConstructions);
        $output = new BufferedOutput();

        self::assertSame(0, new ApplicationConsoleKernel($snapshot)->run(new ArrayInput([
            'command' => 'database:seed',
        ]), $output));
        self::assertSame("Database seeding completed.\n", $output->fetch());
        self::assertSame(1, SeederFixtureState::$rootConstructions);
        self::assertSame(['root:start', 'first:dependency-ready', 'second', 'root:end'], SeederFixtureState::$events);
    }

    public function testStaleContainerBuildIdFailsBeforeRootResolution(): void
    {
        SeederFixtureState::reset();
        $build = $this->directory();
        $class = 'SeederStaleContainer' . bin2hex(random_bytes(8));
        $seeding = [
            'root' => FixtureDatabaseSeeder::class,
            'discovery' => [__DIR__ . '/Fixture/SeederDiscovery'],
        ];
        $fresh = $this->snapshot(
            $build,
            $class,
            'seeder-console-old',
            $seeding,
            [SeederConsoleFixtureServiceProvider::class],
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($fresh))->execute([]));

        $stale = $this->snapshot(
            $build,
            $class,
            'seeder-console-new',
            $seeding,
            [SeederConsoleFixtureServiceProvider::class],
        );
        $output = new BufferedOutput();

        self::assertSame(1, new ApplicationConsoleKernel($stale)->run(new ArrayInput([
            'command' => 'database:seed',
        ]), $output));
        self::assertSame("Database seeding artifacts are unavailable.\n", $output->fetch());
        self::assertSame(0, SeederFixtureState::$rootConstructions);
        self::assertSame([], SeederFixtureState::$events);
    }

    public function testFreshUnconfiguredContainerReturnsOwnedFailure(): void
    {
        $build = $this->directory();
        $snapshot = $this->snapshot(
            $build,
            'SeederEmptyContainer' . bin2hex(random_bytes(8)),
            'seeder-console-empty',
            null,
            [],
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($snapshot))->execute([]));
        $output = new BufferedOutput();

        self::assertSame(1, new ApplicationConsoleKernel($snapshot)->run(new ArrayInput([
            'command' => 'database:seed',
        ]), $output));
        self::assertSame("Database seeding is not configured.\n", $output->fetch());
    }

    /**
     * @param array<array-key, mixed>|null $seeding
     * @param list<class-string<ServiceProvider>> $services
     */
    private function snapshot(
        string $build,
        string $containerClass,
        string $buildId,
        ?array $seeding,
        array $services,
    ): ApplicationConfigurationSnapshot {
        $configuration = [
            'app' => [
                'build' => [
                    'application_build_id' => $buildId,
                    'operation_manifest' => $build . '/operations.php',
                    'http_manifest' => $build . '/http.php',
                    'frontend_manifest' => $build . '/frontend.php',
                    'command_manifest' => $build . '/commands.php',
                    'container' => $build . '/container.php',
                    'container_class' => $containerClass,
                    'container_namespace' => __NAMESPACE__ . '\\Generated',
                ],
            ],
        ];
        if ($seeding !== null) {
            $configuration['database'] = [
                'default' => 'app',
                'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                'seeding' => $seeding,
            ];
        }

        return new ApplicationConfigurationSnapshot(dirname(__DIR__, levels: 3), $configuration, [], $services, []);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class SeederConsoleFixtureServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(SeederFixtureDependency::class);
    }
}
