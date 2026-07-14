<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Console\LazyFrameworkCommand;
use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Console\RetentionPlanCommand;
use BlackOps\Internal\Console\RetentionPurgeCommand;
use BlackOps\Internal\Console\SchedulerDaemonCommand;
use BlackOps\Internal\Console\SchedulerRunCommand;
use BlackOps\Internal\Console\WorkerRunCommand;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ApplicationConsoleKernel
{
    /** @var list<string> */
    private const FRAMEWORK_COMMANDS = [
        ApplicationBuildCompileCommand::NAME,
        ApplicationBuildCompileCommand::LEGACY_NAME,
        ApplicationOperationListCommand::NAME,
        ApplicationOperationListCommand::LEGACY_NAME,
        MakeOperationCommand::NAME,
        MakeMigrationCommand::NAME,
        DatabaseMigrationStatusCommand::NAME,
        DatabaseMigrationStatusCommand::LEGACY_NAME,
        DatabaseMigrationMigrateCommand::NAME,
        DatabaseMigrationMigrateCommand::LEGACY_NAME,
        WorkerRunCommand::NAME,
        WorkerRunCommand::LEGACY_NAME,
        RetentionPlanCommand::NAME,
        RetentionPlanCommand::LEGACY_NAME,
        RetentionPurgeCommand::NAME,
        RetentionPurgeCommand::LEGACY_NAME,
        SchedulerRunCommand::NAME,
        SchedulerRunCommand::LEGACY_NAME,
        SchedulerDaemonCommand::NAME,
        SchedulerDaemonCommand::LEGACY_NAME,
    ];

    private Application $application;

    public function __construct(ApplicationConfigurationSnapshot $configuration)
    {
        $this->application = new Application('BlackOps');
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(false);
        $factory = new ApplicationConsoleCommandFactory($configuration);
        $retention = new ApplicationRetentionCommandFactory($configuration);

        foreach ($this->frameworkCommands($factory, $retention) as $command) {
            $this->application->addCommand($command);
        }

        foreach ($configuration->commands() as $entry) {
            $command = $entry instanceof Command ? $entry : new ReflectionClass($entry)->newInstance();
            $name = $command->getName();
            if ($name !== null) {
                $this->assertApplicationCommandNameIsAvailable($name);
            }

            /** @var list<string> $aliases */
            $aliases = $command->getAliases();
            foreach ($aliases as $alias) {
                $this->assertApplicationCommandNameIsAvailable($alias);
            }

            $this->application->addCommand($command);
        }
    }

    public function run(?InputInterface $input, ?OutputInterface $output): int
    {
        return $this->application->run($input, $output);
    }

    private function assertApplicationCommandNameIsAvailable(string $name): void
    {
        if (in_array($name, self::FRAMEWORK_COMMANDS, strict: true)) {
            throw new InvalidArgumentException(sprintf(
                'Application command name "%s" conflicts with a framework command.',
                $name,
            ));
        }
    }

    /** @return list<LazyFrameworkCommand> */
    private function frameworkCommands(
        ApplicationConsoleCommandFactory $factory,
        ApplicationRetentionCommandFactory $retention,
    ): array {
        $none = static function (Command $command): void {};
        $retentionOptions = static function (Command $command): void {
            $command
                ->addOption('transport-payload-days', null, InputOption::VALUE_REQUIRED)
                ->addOption('journal-days', null, InputOption::VALUE_REQUIRED)
                ->addOption('outcome-days', null, InputOption::VALUE_REQUIRED)
                ->addOption('dead-letter-days', null, InputOption::VALUE_REQUIRED);
        };

        return [
            new LazyFrameworkCommand(
                ApplicationBuildCompileCommand::NAME,
                'Compile application operation, HTTP, and container artifacts.',
                $factory->build(...),
                $none,
                [ApplicationBuildCompileCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                ApplicationOperationListCommand::NAME,
                'List operation metadata from application providers.',
                $factory->operations(...),
                $none,
                [ApplicationOperationListCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                MakeOperationCommand::NAME,
                'Generate a typed self-handled operation.',
                $factory->makeOperation(...),
                static fn(Command $command): Command => $command->addArgument(
                    'operation',
                    InputArgument::REQUIRED,
                    'Feature and action as Feature/Action.',
                )->addOption('type', null, InputOption::VALUE_REQUIRED, 'Stable dot-separated operation type.'),
            ),
            new LazyFrameworkCommand(
                MakeMigrationCommand::NAME,
                'Generate an application database migration.',
                $factory->makeMigration(...),
                static fn(Command $command): Command => $command->addArgument(
                    'description',
                    InputArgument::REQUIRED,
                    'Migration description in PascalCase.',
                ),
            ),
            new LazyFrameworkCommand(
                DatabaseMigrationStatusCommand::NAME,
                'Show applied and pending BlackOps database migrations.',
                $factory->databaseStatus(...),
                $none,
                [DatabaseMigrationStatusCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                DatabaseMigrationMigrateCommand::NAME,
                'Apply or preview BlackOps database migrations.',
                $factory->databaseMigrate(...),
                static fn(Command $command): Command => $command->addOption('dry-run', null, InputOption::VALUE_NONE),
                [DatabaseMigrationMigrateCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                WorkerRunCommand::NAME,
                'Run the deferred operation worker loop.',
                $factory->worker(...),
                static fn(Command $command): Command => $command->addOption(
                    'iterations',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('idle-sleep-milliseconds', null, InputOption::VALUE_REQUIRED, default: '1000'),
                [WorkerRunCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                RetentionPlanCommand::NAME,
                'Build and print a retention purge plan without applying it.',
                $retention->plan(...),
                $retentionOptions,
                [RetentionPlanCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                RetentionPurgeCommand::NAME,
                'Dry-run or apply retention purge.',
                $retention->purge(...),
                static function (Command $command) use ($retentionOptions): void {
                    $retentionOptions($command);
                    $command
                        ->addOption('dry-run', null, InputOption::VALUE_NONE)
                        ->addOption('confirm', null, InputOption::VALUE_NONE)
                        ->addOption('policy-ref', null, InputOption::VALUE_REQUIRED)
                        ->addOption('actor', null, InputOption::VALUE_REQUIRED);
                },
                [RetentionPurgeCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                SchedulerRunCommand::NAME,
                'Run due BlackOps maintenance tasks once.',
                $retention->schedulerRun(...),
                $none,
                [SchedulerRunCommand::LEGACY_NAME],
            ),
            new LazyFrameworkCommand(
                SchedulerDaemonCommand::NAME,
                'Run BlackOps maintenance tasks on an interval.',
                $retention->schedulerDaemon(...),
                static fn(Command $command): Command => $command->addOption(
                    'interval',
                    null,
                    InputOption::VALUE_REQUIRED,
                    default: '60',
                )->addOption('iterations', null, InputOption::VALUE_REQUIRED),
                [SchedulerDaemonCommand::LEGACY_NAME],
            ),
        ];
    }
}
