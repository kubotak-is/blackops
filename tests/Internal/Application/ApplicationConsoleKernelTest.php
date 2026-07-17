<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Internal\Registry\OperationManifestFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationConsoleKernelTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testListsAndHelpsAllFrameworkCommandsWithoutRuntimeConfiguration(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/migrations');
        file_put_contents($directory . '/migrations/Version20260713030101.php', '<?php invalid migration');
        $application = Application::configure($directory)->create();
        $kernel = $application->console();
        self::assertSame($kernel, $application->console());
        $list = new BufferedOutput();

        self::assertSame(0, $kernel->run(new ArrayInput(['command' => 'list']), $list));
        $listing = $list->fetch();

        foreach ([
            'build:compile',
            'operation:list',
            'make:operation',
            'make:migration',
            'database:status',
            'database:migrate',
            'worker:run',
            'retention:plan',
            'retention:purge',
            'scheduler:run',
            'scheduler:daemon',
        ] as $name) {
            self::assertStringContainsString($name, $listing);
        }

        foreach ([
            'blackops:build:compile',
            'blackops:operation:list',
            'blackops:database:status',
            'blackops:database:migrate',
            'blackops:worker:run',
            'blackops:retention:plan',
            'blackops:retention:purge',
            'blackops:scheduler:run',
            'blackops:scheduler:daemon',
        ] as $legacyName) {
            self::assertStringNotContainsString($legacyName, $listing);
        }

        $generatorHelp = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'make:operation',
        ]), $generatorHelp));
        self::assertStringContainsString('Feature/Action', $generatorHelp->fetch());

        $migrationHelp = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'make:migration',
        ]), $migrationHelp));
        self::assertStringContainsString('PascalCase', $migrationHelp->fetch());
    }

    public function testRunsApplicationCommand(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelCustomCommand::class])
            ->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'application:greet',
        ]), $output));
        self::assertSame("hello\n", $output->fetch());
    }

    public function testOperationListRetainsProviderOnlyConfigurationWithoutDiscovery(): void
    {
        $application = Application::configure($this->directory())
            ->withOperations([ConsoleKernelOperationProvider::class])
            ->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'operation:list',
        ]), $output));
        self::assertStringContainsString('console.provider.operation', $output->fetch());
    }

    public function testOperationListResolvesTransactionalOperationFromDatabaseConfiguration(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'database',
            "return ['default' => 'app', 'connections' => ['app' => ['driver' => 'pdo_pgsql']], 'framework' => ['connection' => 'app', 'schema' => 'blackops']];",
        );
        $application = Application::configure($directory)
            ->withConfiguration()
            ->withOperations([ConsoleKernelTransactionalOperationProvider::class])
            ->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'operation:list',
        ]), $output));
        self::assertStringContainsString('console.transactional.operation', $output->fetch());
    }

    public function testRejectsApplicationCommandThatConflictsWithFrameworkCommand(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelConflictingCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with a framework command');

        $application->console();
    }

    public function testRunsApplicationCommandUsingFormerFrameworkAlias(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelFormerFrameworkCommand::class])
            ->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'blackops:worker:run',
        ]), $output));
        self::assertSame("application command\n", $output->fetch());
    }

    public function testRejectsApplicationCommandAliasThatConflictsWithFrameworkCommand(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelAliasConflictingCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with a framework command');

        $application->console();
    }

    public function testRejectsApplicationCommandThatConflictsWithOperationGenerator(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelGeneratorConflictingCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with a framework command');

        $application->console();
    }

    public function testRejectsApplicationCommandThatConflictsWithMigrationGenerator(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelMigrationGeneratorConflictingCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with a framework command');

        $application->console();
    }

    public function testMigrationGeneratorDoesNotResolveDatabaseConfigurationOrBuildArtifacts(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'database',
            "return ['connection' => ['driver' => 'not-a-driver'], 'schema' => 'invalid-schema'];",
        );
        $application = Application::configure($directory)->withConfiguration()->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'make:migration',
            'description' => 'CreateOrdersTable',
        ]), $output));
        self::assertStringContainsString('Created: migrations/Version', $output->fetch());
        self::assertCount(1, glob($directory . '/migrations/Version*.php') ?: []);
        self::assertDirectoryDoesNotExist($directory . '/var/build');
    }

    public function testGeneratedOperationCompilesWithApplicationBuild(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        $build = $directory . '/var/build';
        mkdir($config);
        mkdir($build, recursive: true);
        $feature = 'Generated' . bin2hex(random_bytes(4));
        $action = 'CreateEntry';
        $this->writeConfig(
            $config,
            'app',
            sprintf(
                "return ['build' => ['application_build_id' => 'generator-build', 'operation_manifest' => '%s', 'http_manifest' => '%s', 'container' => '%s', 'container_class' => 'CompiledContainer', 'container_namespace' => 'App\\\\Generated']];",
                $build . '/operations.php',
                $build . '/http.php',
                $build . '/container.php',
            ),
        );
        $this->writeConfig(
            $config,
            'operations',
            sprintf("return ['discovery' => ['%s'], 'providers' => []];", $directory . '/app/Feature'),
        );
        mkdir($directory . '/migrations');
        file_put_contents($directory . '/migrations/Version20260713030201.php', '<?php invalid migration');
        $application = Application::configure($directory)->withConfiguration()->create();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'make:operation',
            'operation' => $feature . '/' . $action,
            '--type' => 'generated.create',
        ]), new BufferedOutput()));
        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'build:compile',
        ]), new BufferedOutput()));

        self::assertFileExists($build . '/operations.php');
        self::assertFileExists($build . '/http.php');
        self::assertFileExists($build . '/container.php');
        self::assertSame(
            'App\\Feature\\' . $feature . '\\' . $action . '\\' . $action,
            new OperationManifestFile()
                ->load($build . '/operations.php')
                ->findByTypeId('generated.create')
                ?->definition,
        );
    }

    public function testDatabaseCommandRejectsApplicationMigrationParseErrorWithoutExposingPath(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'database',
            "return ['connection' => ['driver' => 'pdo_pgsql', 'host' => 'postgres', 'port' => 5432, 'dbname' => 'blackops', 'user' => 'blackops', 'password' => 'blackops'], 'schema' => 'blackops_application_parse_error'];",
        );
        mkdir($directory . '/migrations');
        file_put_contents($directory . '/migrations/Version20260713030301.php', '<?php invalid migration');
        $application = Application::configure($directory)->withConfiguration()->create();

        try {
            $application->console()->run(new ArrayInput([
                'command' => 'database:status',
            ]), new BufferedOutput());
            self::fail('Expected application migration parse error.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application console command failed.', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCommandFactoryErrorDoesNotExposeConnectionCredential(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        $credential = 'credential-that-must-not-appear';
        mkdir($config);
        $this->writeConfig(
            $config,
            'database',
            sprintf("return ['connection' => ['password' => '%s'], 'schema' => 'invalid-schema'];", $credential),
        );
        $application = Application::configure($directory)->withConfiguration()->create();

        try {
            $application->console()->run(new ArrayInput([
                'command' => 'database:status',
            ]), new BufferedOutput());
            self::fail('Expected invalid database configuration.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('database.schema', $exception->getMessage());
            self::assertStringNotContainsString($credential, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}

final class ConsoleKernelCustomCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:greet');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('hello');

        return Command::SUCCESS;
    }
}

final class ConsoleKernelConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('worker:run');
    }
}

final class ConsoleKernelFormerFrameworkCommand extends Command
{
    public function __construct()
    {
        parent::__construct('blackops:worker:run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('application command');

        return Command::SUCCESS;
    }
}

final class ConsoleKernelAliasConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:conflict');
        $this->setAliases(['database:status']);
    }
}

final class ConsoleKernelGeneratorConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:operation');
    }
}

final class ConsoleKernelMigrationGeneratorConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:migration');
    }
}

final readonly class ConsoleKernelOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ConsoleKernelProviderOperation::class];
    }
}

final readonly class ConsoleKernelTransactionalOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ConsoleKernelTransactionalOperation::class];
    }
}

#[OperationType('console.transactional.operation')]
#[Transactional]
readonly class ConsoleKernelTransactionalOperation implements Operation
{
    public function handle(ConsoleKernelProviderValue $value): void {}
}

#[OperationType('console.provider.operation')]
#[Accepts(ConsoleKernelProviderValue::class)]
#[HandledBy(ConsoleKernelProviderHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class ConsoleKernelProviderOperation implements Operation {}

final readonly class ConsoleKernelProviderValue implements OperationValue {}

final readonly class ConsoleKernelProviderHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
