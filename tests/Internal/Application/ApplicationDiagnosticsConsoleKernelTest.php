<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Application\ApplicationDiagnosticsQueryFactory;
use BlackOps\Internal\Console\OperationInspectCommand;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Tests\Internal\Console\OperationInspectFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationDiagnosticsConsoleKernelTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testListHelpAndInvalidInputRemainDatabaseIndependent(): void
    {
        $application = Application::configure($this->directory())->create();
        $kernel = $application->console();
        $list = new BufferedOutput();

        self::assertSame(0, $kernel->run(new ArrayInput(['command' => 'list']), $list));
        self::assertStringContainsString('operation:inspect', $list->fetch());

        $help = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'operation:inspect',
        ]), $help));
        $helpText = $help->fetch();
        self::assertStringContainsString('operation:inspect <operation-id> [--json]', $helpText);
        self::assertStringNotContainsString('operation:inspect [<operation-id>]', $helpText);

        foreach ([[], ['operation-id' => 'invalid']] as $arguments) {
            $invalid = new BufferedOutput();
            self::assertSame(2, $kernel->run(new ArrayInput([
                'command' => 'operation:inspect',
                ...$arguments,
            ]), $invalid));
            self::assertSame("operation.invalid_id\n", $invalid->fetch());
        }
    }

    public function testApplicationCommandNameAndAliasCannotUseOperationInspect(): void
    {
        foreach ([
            ApplicationDiagnosticsConflictingCommand::class,
            ApplicationDiagnosticsAliasConflictingCommand::class,
        ] as $command) {
            try {
                Application::configure($this->directory())->withCommands([$command])->create()->console();
                self::fail('Expected framework command conflict.');
            } catch (ApplicationBootstrapException $exception) {
                self::assertStringContainsString('conflicts with a framework command', $exception->getMessage());
            }
        }
    }

    public function testUnavailableFrameworkDatabaseUsesOnlyTheSafeStorageCode(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'database',
            "return ['default' => 'app', 'connections' => ['app' => ['driver' => 'pdo_pgsql'], 'framework' => ['driver' => 'pdo_pgsql', 'host' => '127.0.0.1', 'port' => 1, 'dbname' => 'private', 'user' => 'private', 'password' => 'credential-that-must-not-appear']], 'framework' => ['connection' => 'framework', 'schema' => 'blackops']];",
        );
        $application = Application::configure($directory)->withConfiguration()->create();
        $factory = new ApplicationDiagnosticsQueryFactory($this->snapshot($application));
        $tester = new CommandTester(new OperationInspectCommand(
            static fn(OperationId $id): OperationDiagnosticsResult => $factory->create()->find($id),
        ));

        self::assertSame(4, $tester->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame(
            "{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"diagnostics.storage_failed\"}\n",
            $tester->getErrorOutput(),
        );
    }

    public function testFormerPrefixedNameIsNotReservedAndUnknownCommandRemainsUnknown(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ApplicationDiagnosticsFormerNameCommand::class])
            ->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'blackops:operation:inspect',
        ]), $output));
        self::assertSame("application command\n", $output->fetch());

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('Command "operation:unknown" is not defined.');
        Application::configure($this->directory())
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'operation:unknown']), new BufferedOutput());
    }
}

final class ApplicationDiagnosticsConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('operation:inspect');
    }
}

final class ApplicationDiagnosticsAliasConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:diagnostics');
        $this->setAliases(['operation:inspect']);
    }
}

final class ApplicationDiagnosticsFormerNameCommand extends Command
{
    public function __construct()
    {
        parent::__construct('blackops:operation:inspect');
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output,
    ): int {
        $output->writeln('application command');

        return Command::SUCCESS;
    }
}
