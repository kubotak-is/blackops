<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Internal\Console\ApplicationCommandManifestFile;
use BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery\CommandDiscoveryServiceProvider;
use BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery\ConfiguredCommandGreeting;
use BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery\DiscoveredGreetingCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationCommandDiscoveryIntegrationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testBuildsAndLazilyRunsContainerInjectedDiscoveredCommand(): void
    {
        $directory = $this->applicationDirectory('CommandContainer' . bin2hex(random_bytes(4)));
        DiscoveredGreetingCommand::$constructions = 0;
        $application = Application::configure($directory)->withConfiguration()->create();
        $buildOutput = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput(['command' => 'build:compile']), $buildOutput));
        self::assertSame(0, DiscoveredGreetingCommand::$constructions);
        self::assertStringContainsString('Build artifacts written.', $buildOutput->fetch());
        $artifact = new ApplicationCommandManifestFile()->loadArtifact($directory . '/var/build/commands.php');
        self::assertSame('command-fixture', $artifact->applicationBuildId);
        self::assertSame(
            [DiscoveredGreetingCommand::class],
            array_map(static fn($command): string => $command->class, $artifact->commands),
        );

        $runtime = Application::configure($directory)->withConfiguration()->create();
        $list = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput(['command' => 'list']), $list));
        self::assertStringContainsString('fixture:greet', $list->fetch());
        self::assertSame(0, DiscoveredGreetingCommand::$constructions);

        $help = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'fixture:greet',
        ]), $help));
        self::assertStringContainsString('proves lazy container-backed command resolution', $help->fetch());
        self::assertSame(1, DiscoveredGreetingCommand::$constructions);

        $run = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput(['command' => 'fixture:hello']), $run));
        self::assertSame("container dependency ready\n", $run->fetch());
        self::assertSame(1, DiscoveredGreetingCommand::$constructions);
    }

    public function testMissingInvalidAndStaleArtifactsKeepRecoveryBuildAvailable(): void
    {
        $directory = $this->applicationDirectory('RecoveryContainer' . bin2hex(random_bytes(4)));
        $commands = $directory . '/var/build/commands.php';
        $list = new BufferedOutput();

        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'list']), $list));
        self::assertStringContainsString('build:compile', $list->fetch());

        file_put_contents($commands, '<?php return "credential-value";');
        $invalid = new BufferedOutput();
        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'list']), $invalid));
        self::assertStringContainsString('build:compile', $invalid->fetch());
        self::assertStringNotContainsString('credential-value', $invalid->fetch());

        new ApplicationCommandManifestFile()->write([], $commands, 'stale-build');
        $stale = new BufferedOutput();
        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'list']), $stale));
        self::assertStringContainsString('build:compile', $stale->fetch());
    }

    public function testExplicitInstanceOfSameClassOverridesDiscoveredRegistration(): void
    {
        $directory = $this->applicationDirectory('OverrideContainer' . bin2hex(random_bytes(4)));
        $explicit = new DiscoveredGreetingCommand(new ConfiguredCommandGreeting());
        $application = Application::configure($directory)->withConfiguration()->withCommands([$explicit])->create();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'build:compile',
        ]), new BufferedOutput()));
        self::assertSame(
            [],
            new ApplicationCommandManifestFile()->loadArtifact($directory . '/var/build/commands.php')->commands,
        );

        $output = new BufferedOutput();
        self::assertSame(0, $application->console()->run(new ArrayInput(['command' => 'fixture:greet']), $output));
        self::assertSame("container dependency ready\n", $output->fetch());
    }

    public function testSuccessfulEmptyDiscoveryBuildRemovesStaleCommandManifestEntries(): void
    {
        $containerClass = 'EmptyDiscoveryContainer' . bin2hex(random_bytes(4));
        $directory = $this->applicationDirectory($containerClass);
        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'build:compile']), new BufferedOutput()));
        self::assertCount(
            1,
            new ApplicationCommandManifestFile()->loadArtifact($directory . '/var/build/commands.php')->commands,
        );

        $this->writeApplicationConfig($directory, $containerClass, null, [CommandDiscoveryServiceProvider::class]);
        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'build:compile']), new BufferedOutput()));
        self::assertSame(
            [],
            new ApplicationCommandManifestFile()->loadArtifact($directory . '/var/build/commands.php')->commands,
        );
    }

    public function testValidManifestCollisionAfterExplicitConfigurationChangeIsBootstrapError(): void
    {
        $directory = $this->applicationDirectory('RuntimeCollisionContainer' . bin2hex(random_bytes(4)));
        self::assertSame(0, Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'build:compile']), new BufferedOutput()));
        $changed = Application::configure($directory)
            ->withConfiguration()
            ->withCommands([RuntimeCollisionCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with another command');

        $changed->console();
    }

    public function testValidManifestListsWithoutContainerAndFailsSafelyOnlyWhenCommandRuns(): void
    {
        $directory = $this->applicationDirectory('MissingContainer' . bin2hex(random_bytes(4)));
        $application = Application::configure($directory)->withConfiguration()->create();
        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'build:compile',
        ]), new BufferedOutput()));
        unlink($directory . '/var/build/container.php');
        DiscoveredGreetingCommand::$constructions = 0;
        $runtime = Application::configure($directory)->withConfiguration()->create();
        $list = new BufferedOutput();

        self::assertSame(0, $runtime->console()->run(new ArrayInput(['command' => 'list']), $list));
        self::assertStringContainsString('fixture:greet', $list->fetch());
        self::assertSame(0, DiscoveredGreetingCommand::$constructions);

        try {
            $runtime->console()->run(new ArrayInput(['command' => 'fixture:greet']), new BufferedOutput());
            self::fail('Expected missing container failure.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application command service could not be resolved.', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
        }
    }

    public function testUnresolvedConstructorDependencyFailsBuildWithoutReplacingExistingManifest(): void
    {
        $directory = $this->directory();
        $source = $directory . '/app';
        $build = $directory . '/var/build';
        mkdir($source);
        mkdir($build, recursive: true);
        $namespace = 'BlackOps\\Tests\\Generated\\Unresolved' . bin2hex(random_bytes(4));
        file_put_contents($source . '/Unresolved.php', <<<PHP
            <?php
            namespace {$namespace};
            use Symfony\Component\Console\Attribute\AsCommand;
            use Symfony\Component\Console\Command\Command;
            interface MissingDependency {}
            #[AsCommand(name: 'fixture:unresolved')]
            final class UnresolvedCommand extends Command {
                public function __construct(MissingDependency \$dependency) { parent::__construct(); }
            }
            PHP);
        $this->writeApplicationConfig($directory, 'UnresolvedContainer' . bin2hex(random_bytes(4)), $source, []);
        $manifest = $build . '/commands.php';
        file_put_contents($manifest, 'previous-manifest');

        try {
            Application::configure($directory)
                ->withConfiguration()
                ->create()
                ->console()
                ->run(new ArrayInput(['command' => 'build:compile']), new BufferedOutput());
            self::fail('Expected unresolved command dependency build failure.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application console command failed.', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
        }
        self::assertSame('previous-manifest', file_get_contents($manifest));
    }

    private function applicationDirectory(string $containerClass): string
    {
        $directory = $this->directory();
        mkdir($directory . '/var/build', recursive: true);
        $this->writeApplicationConfig(
            $directory,
            $containerClass,
            __DIR__ . '/Fixture/CommandDiscovery',
            [CommandDiscoveryServiceProvider::class],
        );

        return $directory;
    }

    /** @param list<class-string> $services */
    private function writeApplicationConfig(
        string $directory,
        string $containerClass,
        ?string $discovery,
        array $services,
    ): void {
        $config = $directory . '/config';
        if (!is_dir($config)) {
            mkdir($config);
        }
        $build = $directory . '/var/build';
        $this->writeConfig(
            $config,
            'app',
            'return '
            . var_export([
                'build' => [
                    'application_build_id' => 'command-fixture',
                    'operation_manifest' => $build . '/operations.php',
                    'http_manifest' => $build . '/http.php',
                    'frontend_manifest' => $build . '/frontend.php',
                    'command_manifest' => $build . '/commands.php',
                    'container' => $build . '/container.php',
                    'container_class' => $containerClass,
                    'container_namespace' => 'BlackOps\\Tests\\Generated\\Container',
                ],
                'command_discovery' => $discovery === null ? [] : [$discovery],
                'services' => $services,
                'commands' => [],
            ], return: true)
            . ';',
        );
    }
}

final class RuntimeCollisionCommand extends Command
{
    public function __construct()
    {
        parent::__construct('fixture:greet');
    }
}
