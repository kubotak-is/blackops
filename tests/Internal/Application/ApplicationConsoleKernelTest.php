<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
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
        $application = Application::configure($this->directory())->create();
        $kernel = $application->console();
        self::assertSame($kernel, $application->console());
        $list = new BufferedOutput();

        self::assertSame(0, $kernel->run(new ArrayInput(['command' => 'list']), $list));
        $listing = $list->fetch();

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
        ] as $name) {
            self::assertStringContainsString($name, $listing);
        }

        $help = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'blackops:worker:run',
        ]), $help));
        self::assertStringContainsString('blackops:worker:run', $help->fetch());
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

    public function testRejectsApplicationCommandThatConflictsWithFrameworkCommand(): void
    {
        $application = Application::configure($this->directory())
            ->withCommands([ConsoleKernelConflictingCommand::class])
            ->create();

        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('conflicts with a framework command');

        $application->console();
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
                'command' => 'blackops:database:status',
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
        parent::__construct('blackops:worker:run');
    }
}
