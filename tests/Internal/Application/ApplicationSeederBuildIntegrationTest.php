<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Seeder\CompiledSeederRuntime;
use BlackOps\Internal\Seeder\SeederRuntimeException;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\FixtureDatabaseSeeder;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\SeederFixtureDependency;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\SeederFixtureState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/Fixture/SeederDiscovery/FixtureSeeders.php';

final class ApplicationSeederBuildIntegrationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testBuildDiscoversPrivateSeedersAndRunsConfiguredRootWithConstructorInjection(): void
    {
        SeederFixtureState::reset();
        $buildDirectory = $this->directory();
        $class = 'SeederContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $snapshot = $this->snapshotFor(
            dirname(path: __DIR__, levels: 3),
            $buildDirectory,
            $class,
            [
                'root' => FixtureDatabaseSeeder::class,
                'discovery' => [__DIR__ . '/Fixture/SeederDiscovery'],
            ],
            [SeederFixtureServiceProvider::class],
        );

        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($snapshot))->execute([]));
        self::assertSame(0, SeederFixtureState::$rootConstructions);
        self::assertSame(0, SeederFixtureState::$childConstructions);

        $containerPath = $buildDirectory . '/container.php';
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertFalse($container->has(FixtureDatabaseSeeder::class));
        $runtime = $container->get(CompiledSeederRuntime::class);
        self::assertInstanceOf(CompiledSeederRuntime::class, $runtime);
        self::assertTrue($runtime->configured());

        $runtime->run();

        self::assertSame(1, SeederFixtureState::$rootConstructions);
        self::assertSame(2, SeederFixtureState::$childConstructions);
        self::assertSame(['root:start', 'first:dependency-ready', 'second', 'root:end'], SeederFixtureState::$events);
    }

    public function testMissingStandardSeederBuildsAsUnconfiguredRuntime(): void
    {
        $basePath = $this->directory();
        $buildDirectory = $basePath . '/build';
        mkdir($buildDirectory);
        $class = 'EmptySeederContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $snapshot = $this->snapshotFor($basePath, $buildDirectory, $class, null, []);

        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($snapshot))->execute([]));

        require_once $buildDirectory . '/container.php';
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();
        $runtime = $container->get(CompiledSeederRuntime::class);

        self::assertInstanceOf(CompiledSeederRuntime::class, $runtime);
        self::assertFalse($runtime->configured());
        $this->expectException(SeederRuntimeException::class);
        $this->expectExceptionMessage('Database seeding is not configured.');

        $runtime->run();
    }

    public function testUnresolvedSeederDependencyPreservesEveryExistingBuildArtifact(): void
    {
        $basePath = $this->directory();
        $source = $basePath . '/app/Database/Seed';
        $buildDirectory = $basePath . '/build';
        mkdir($source, recursive: true);
        mkdir($buildDirectory);
        $namespace = 'BlackOps\\Tests\\Generated\\SeederFailure' . bin2hex(random_bytes(8));
        $root = $namespace . '\\BrokenSeeder';
        file_put_contents($source . '/BrokenSeeder.php', <<<PHP
            <?php
            declare(strict_types=1);
            namespace {$namespace};
            use BlackOps\Database\Seeder;
            interface MissingSeederDependency {}
            final readonly class BrokenSeeder implements Seeder
            {
                public function __construct(private MissingSeederDependency \$dependency) {}
                public function run(): void {}
            }
            PHP);
        $snapshot = $this->snapshotFor(
            $basePath,
            $buildDirectory,
            'FailedSeederContainer',
            [
                'root' => $root,
                'discovery' => [$source],
            ],
            [],
        );
        $sentinels = [];
        foreach (['operations.php', 'http.php', 'frontend.php', 'commands.php', 'container.php'] as $artifact) {
            $sentinels[$artifact] = 'existing-' . $artifact;
            file_put_contents($buildDirectory . '/' . $artifact, $sentinels[$artifact]);
        }
        mkdir($buildDirectory . '/aop');
        file_put_contents($buildDirectory . '/aop/existing.php', 'existing-aop');

        $failed = false;
        try {
            new CommandTester(new ApplicationBuildCompileCommand($snapshot))->execute([]);
        } catch (\Throwable) {
            $failed = true;
        }

        self::assertTrue($failed, 'Expected unresolved seeder dependency build failure.');
        foreach ($sentinels as $artifact => $sentinel) {
            self::assertSame($sentinel, file_get_contents($buildDirectory . '/' . $artifact));
        }
        self::assertSame('existing-aop', file_get_contents($buildDirectory . '/aop/existing.php'));
    }

    /**
     * @param array<array-key, mixed>|null $seeding
     * @param list<class-string<ServiceProvider>> $services
     */
    private function snapshotFor(
        string $basePath,
        string $buildDirectory,
        string $class,
        ?array $seeding,
        array $services,
    ): ApplicationConfigurationSnapshot {
        $namespace = __NAMESPACE__ . '\\Generated';
        $configuration = [
            'app' => [
                'build' => [
                    'application_build_id' => 'seeder-build',
                    'operation_manifest' => $buildDirectory . '/operations.php',
                    'http_manifest' => $buildDirectory . '/http.php',
                    'frontend_manifest' => $buildDirectory . '/frontend.php',
                    'command_manifest' => $buildDirectory . '/commands.php',
                    'container' => $buildDirectory . '/container.php',
                    'container_class' => $class,
                    'container_namespace' => $namespace,
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

        return new ApplicationConfigurationSnapshot($basePath, $configuration, [], $services, []);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class SeederFixtureServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(SeederFixtureDependency::class);
    }
}
