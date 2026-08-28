<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Console\ApplicationCommandCollisionValidator;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Console\DatabaseSeedCommand;
use BlackOps\Internal\Console\FrameworkCommandNames;
use BlackOps\Internal\Console\FrontendCheckCommand;
use BlackOps\Internal\Console\FrontendGenerateCommand;
use BlackOps\Internal\Console\JournalObserverReplayCommand;
use BlackOps\Internal\Console\LazyFrameworkCommand;
use BlackOps\Internal\Console\MakeAuthCommand;
use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Console\MakeSeederCommand;
use BlackOps\Internal\Console\OperationConsoleCommand;
use BlackOps\Internal\Console\OperationInspectCommand;
use BlackOps\Internal\Console\OperationViewerCommand;
use BlackOps\Internal\Console\OutboxDeadLetterRetryCommand;
use BlackOps\Internal\Console\OutboxRelayDaemonCommand;
use BlackOps\Internal\Console\OutboxRelayRunCommand;
use BlackOps\Internal\Console\RetentionPlanCommand;
use BlackOps\Internal\Console\RetentionPurgeCommand;
use BlackOps\Internal\Console\SchedulerDaemonCommand;
use BlackOps\Internal\Console\SchedulerRunCommand;
use BlackOps\Internal\Console\WorkerRunCommand;
use BlackOps\Internal\Projection\Command\CommandProjectionListCommand;
use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;
use BlackOps\Internal\Projection\Route\RouteProjectionListCommand;
use BlackOps\Internal\Projection\Schedule\ScheduleProjectionListCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ApplicationConsoleKernel
{
    private Application $application;

    public function __construct(ApplicationConfigurationSnapshot $configuration)
    {
        $this->application = new Application('BlackOps');
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(false);
        $factory = new ApplicationConsoleCommandFactory($configuration);
        $retention = new ApplicationRetentionCommandFactory($configuration);
        $statusSource = new ApplicationProjectionBuildSetStatusSource($configuration);

        $this->application->addCommand(new CommandProjectionListCommand(statusSource: $statusSource));
        $this->application->addCommand(new RouteProjectionListCommand(statusSource: $statusSource));
        $this->application->addCommand(new ScheduleProjectionListCommand(statusSource: $statusSource));

        foreach ($this->frameworkCommands($factory, $retention) as $command) {
            $this->application->addCommand($command);
        }

        $explicit = ExplicitApplicationCommands::from($configuration->commands());
        $explicitMetadata = $explicit->metadata();
        $frameworkNames = FrameworkCommandNames::all();
        new ApplicationCommandCollisionValidator()->merge([], $explicitMetadata, $frameworkNames);
        foreach ($explicit->commands() as $command) {
            $this->application->addCommand($command);
        }

        $manifest = new ApplicationCommandRuntimeManifestLoader()->load(
            $configuration,
            $explicitMetadata,
            $frameworkNames,
        );
        if ($manifest !== null) {
            $resolver = new ApplicationCommandContainerResolver($configuration, $manifest->build);
            foreach ($manifest->commands as $command) {
                $this->application->addCommand(new LazyCommand(
                    $command->name,
                    $command->aliases,
                    $command->description ?? '',
                    $command->hidden,
                    static fn(): Command => $resolver->resolve($command->class),
                ));
            }
            foreach ($manifest->operationCommands as $command) {
                $this->application->addCommand(
                    new OperationConsoleCommand($command, static fn() => new ApplicationOperationConsoleRuntimeComposer()->compose(
                        $configuration,
                        $command,
                    )),
                );
            }
        }
    }

    public function run(?InputInterface $input, ?OutputInterface $output): int
    {
        return $this->application->run($input, $output);
    }

    /** @return list<Command> */
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
                ->addOption('dead-letter-days', null, InputOption::VALUE_REQUIRED)
                ->addOption('idempotency-record-days', null, InputOption::VALUE_REQUIRED);
        };
        $scheduledOperationRun = $factory->scheduledOperationRun();
        $storageCommands = ApplicationStorageProtectionCommands::create($factory);

        $commands = [
            $factory->diagnostics(),
            $factory->queueStatus(),
            $factory->about(),
            $factory->outboxStatus(),
            new LazyFrameworkCommand(
                OperationViewerCommand::NAME,
                CommandProjectionTargetRegistry::description(OperationViewerCommand::NAME),
                $factory->operationViewer(...),
                $none,
            ),
            new LazyFrameworkCommand(
                OperationInspectCommand::NAME,
                CommandProjectionTargetRegistry::description(OperationInspectCommand::NAME),
                $factory->operationInspect(...),
                static fn(Command $command): Command => $command
                    ->addArgument(
                        'operation-id',
                        InputArgument::OPTIONAL,
                        'Required UUID version 7 operation identifier.',
                    )
                    ->addOption('json', null, InputOption::VALUE_NONE, 'Write a versioned JSON object.')
                    ->addUsage('<operation-id> [--json]'),
            )->withCanonicalSynopsis('operation:inspect <operation-id> [--json]'),
            new LazyFrameworkCommand(
                ApplicationBuildCompileCommand::NAME,
                CommandProjectionTargetRegistry::description(ApplicationBuildCompileCommand::NAME),
                $factory->build(...),
                $none,
            ),
            new LazyFrameworkCommand(
                FrontendGenerateCommand::NAME,
                CommandProjectionTargetRegistry::description(FrontendGenerateCommand::NAME),
                $factory->frontendGenerate(...),
                $none,
            ),
            new LazyFrameworkCommand(
                FrontendCheckCommand::NAME,
                CommandProjectionTargetRegistry::description(FrontendCheckCommand::NAME),
                $factory->frontendCheck(...),
                $none,
            ),
            new LazyFrameworkCommand(
                ApplicationOperationListCommand::NAME,
                CommandProjectionTargetRegistry::description(ApplicationOperationListCommand::NAME),
                $factory->operations(...),
                static fn(Command $command): Command => $command->addOption(
                    'strategy',
                    null,
                    InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                    'Filter by exact built-in execution strategy.',
                ),
            ),
            new LazyFrameworkCommand(
                MakeOperationCommand::NAME,
                CommandProjectionTargetRegistry::description(MakeOperationCommand::NAME),
                $factory->makeOperation(...),
                static fn(Command $command): Command => $command->addArgument(
                    'operation',
                    InputArgument::REQUIRED,
                    'Feature and action as Feature/Action.',
                )->addOption('type', null, InputOption::VALUE_REQUIRED, 'Stable dot-separated operation type.'),
            ),
            new LazyFrameworkCommand(
                MakeMigrationCommand::NAME,
                CommandProjectionTargetRegistry::description(MakeMigrationCommand::NAME),
                $factory->makeMigration(...),
                static fn(Command $command): Command => $command->addArgument(
                    'description',
                    InputArgument::REQUIRED,
                    'Migration description in PascalCase.',
                ),
            ),
            new LazyFrameworkCommand(
                MakeSeederCommand::NAME,
                CommandProjectionTargetRegistry::description(MakeSeederCommand::NAME),
                $factory->makeSeeder(...),
                static fn(Command $command): Command => $command->addArgument(
                    'name',
                    InputArgument::REQUIRED,
                    'Seeder name as one or more PascalCase segments.',
                ),
            ),
            new LazyFrameworkCommand(
                MakeAuthCommand::NAME,
                CommandProjectionTargetRegistry::description(MakeAuthCommand::NAME),
                $factory->makeAuth(...),
                static fn(Command $command): Command => $command->addOption(
                    'force',
                    null,
                    InputOption::VALUE_NONE,
                    'Update framework-owned authentication files.',
                ),
            ),
            new LazyFrameworkCommand(
                DatabaseMigrationStatusCommand::NAME,
                CommandProjectionTargetRegistry::description(DatabaseMigrationStatusCommand::NAME),
                $factory->databaseStatus(...),
                $none,
            ),
            new LazyFrameworkCommand(
                DatabaseMigrationMigrateCommand::NAME,
                CommandProjectionTargetRegistry::description(DatabaseMigrationMigrateCommand::NAME),
                $factory->databaseMigrate(...),
                static fn(Command $command): Command => $command->addOption('dry-run', null, InputOption::VALUE_NONE),
            ),
            new LazyFrameworkCommand(
                DatabaseSeedCommand::NAME,
                CommandProjectionTargetRegistry::description(DatabaseSeedCommand::NAME),
                $factory->databaseSeed(...),
                $none,
            ),
            new LazyFrameworkCommand(
                WorkerRunCommand::NAME,
                CommandProjectionTargetRegistry::description(WorkerRunCommand::NAME),
                $factory->worker(...),
                static fn(Command $command): Command => $command->addOption(
                    'iterations',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('idle-sleep-milliseconds', null, InputOption::VALUE_REQUIRED, default: '1000'),
            ),
            $scheduledOperationRun,
            new LazyFrameworkCommand(
                RetentionPlanCommand::NAME,
                CommandProjectionTargetRegistry::description(RetentionPlanCommand::NAME),
                $retention->plan(...),
                $retentionOptions,
            ),
            new LazyFrameworkCommand(
                RetentionPurgeCommand::NAME,
                CommandProjectionTargetRegistry::description(RetentionPurgeCommand::NAME),
                $retention->purge(...),
                static function (Command $command) use ($retentionOptions): void {
                    $retentionOptions($command);
                    $command
                        ->addOption('dry-run', null, InputOption::VALUE_NONE)
                        ->addOption('confirm', null, InputOption::VALUE_NONE)
                        ->addOption('policy-ref', null, InputOption::VALUE_REQUIRED)
                        ->addOption('actor', null, InputOption::VALUE_REQUIRED);
                },
            ),
            new LazyFrameworkCommand(
                SchedulerRunCommand::NAME,
                CommandProjectionTargetRegistry::description(SchedulerRunCommand::NAME),
                $retention->schedulerRun(...),
                $none,
            ),
            new LazyFrameworkCommand(
                SchedulerDaemonCommand::NAME,
                CommandProjectionTargetRegistry::description(SchedulerDaemonCommand::NAME),
                $retention->schedulerDaemon(...),
                static fn(Command $command): Command => $command->addOption(
                    'interval',
                    null,
                    InputOption::VALUE_REQUIRED,
                    default: '60',
                )->addOption('iterations', null, InputOption::VALUE_REQUIRED),
            ),
            new LazyFrameworkCommand(
                OutboxRelayRunCommand::NAME,
                CommandProjectionTargetRegistry::description(OutboxRelayRunCommand::NAME),
                $factory->outboxRelayRun(...),
                static fn(Command $command): Command => $command->addOption(
                    'batches',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('until-empty', null, InputOption::VALUE_NONE),
            ),
            new LazyFrameworkCommand(
                OutboxRelayDaemonCommand::NAME,
                CommandProjectionTargetRegistry::description(OutboxRelayDaemonCommand::NAME),
                $factory->outboxRelayDaemon(...),
                static fn(Command $command): Command => $command->addOption(
                    'interval-milliseconds',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('iterations', null, InputOption::VALUE_REQUIRED),
            ),
            new LazyFrameworkCommand(
                OutboxDeadLetterRetryCommand::NAME,
                CommandProjectionTargetRegistry::description(OutboxDeadLetterRetryCommand::NAME),
                $factory->outboxDeadLetterRetry(...),
                static fn(Command $command): Command => $command
                    ->addArgument('record-id', InputArgument::REQUIRED)
                    ->addOption('actor', null, InputOption::VALUE_REQUIRED)
                    ->addOption('reason', null, InputOption::VALUE_REQUIRED),
            ),
            new LazyFrameworkCommand(
                JournalObserverReplayCommand::NAME,
                CommandProjectionTargetRegistry::description(JournalObserverReplayCommand::NAME),
                $factory->journalObserverReplay(...),
                static fn(Command $command): Command => $command
                    ->addOption('operation-id', null, InputOption::VALUE_REQUIRED)
                    ->addOption('record-id', null, InputOption::VALUE_REQUIRED)
                    ->addOption('from', null, InputOption::VALUE_REQUIRED)
                    ->addOption('to', null, InputOption::VALUE_REQUIRED)
                    ->addOption('observer', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)
                    ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, default: '100')
                    ->addOption('dry-run', null, InputOption::VALUE_NONE)
                    ->addOption('confirm', null, InputOption::VALUE_NONE)
                    ->addOption('checkpoint', null, InputOption::VALUE_REQUIRED)
                    ->addOption('resume', null, InputOption::VALUE_REQUIRED)
                    ->addOption('actor', null, InputOption::VALUE_REQUIRED)
                    ->addOption('reason', null, InputOption::VALUE_REQUIRED),
            ),
            ...$storageCommands,
        ];

        return array_map(self::applyFrameworkMetadata(...), $commands);
    }

    private static function applyFrameworkMetadata(Command $command): Command
    {
        $name = $command->getName();
        if ($name === null) {
            throw new \LogicException('Framework command must have a name.');
        }
        $target = CommandProjectionTargetRegistry::target($name);

        return $command->setAliases($target->aliases)->setDescription($target->description)->setHidden($target->hidden);
    }
}
