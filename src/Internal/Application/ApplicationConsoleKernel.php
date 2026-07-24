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
                OperationViewerCommand::NAME,
                'Start the read-only local operation diagnostics viewer.',
                $factory->operationViewer(...),
                $none,
            ),
            new LazyFrameworkCommand(
                OperationInspectCommand::NAME,
                'Inspect one operation lifecycle and outcome.',
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
                'Compile application operation, HTTP, command, and container artifacts.',
                $factory->build(...),
                $none,
            ),
            new LazyFrameworkCommand(
                FrontendGenerateCommand::NAME,
                'Generate TypeScript operation objects from the frontend contract.',
                $factory->frontendGenerate(...),
                $none,
            ),
            new LazyFrameworkCommand(
                FrontendCheckCommand::NAME,
                'Check generated TypeScript operation objects for drift.',
                $factory->frontendCheck(...),
                $none,
            ),
            new LazyFrameworkCommand(
                ApplicationOperationListCommand::NAME,
                'List operation metadata from application providers.',
                $factory->operations(...),
                $none,
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
                MakeSeederCommand::NAME,
                'Generate an application database seeder.',
                $factory->makeSeeder(...),
                static fn(Command $command): Command => $command->addArgument(
                    'name',
                    InputArgument::REQUIRED,
                    'Seeder name as one or more PascalCase segments.',
                ),
            ),
            new LazyFrameworkCommand(
                MakeAuthCommand::NAME,
                'Generate a bearer session authentication starter.',
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
                'Show applied and pending BlackOps database migrations.',
                $factory->databaseStatus(...),
                $none,
            ),
            new LazyFrameworkCommand(
                DatabaseMigrationMigrateCommand::NAME,
                'Apply or preview BlackOps database migrations.',
                $factory->databaseMigrate(...),
                static fn(Command $command): Command => $command->addOption('dry-run', null, InputOption::VALUE_NONE),
            ),
            new LazyFrameworkCommand(
                DatabaseSeedCommand::NAME,
                'Run the compiled application database root seeder.',
                $factory->databaseSeed(...),
                $none,
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
            ),
            new LazyFrameworkCommand(
                RetentionPlanCommand::NAME,
                'Build and print a retention purge plan without applying it.',
                $retention->plan(...),
                $retentionOptions,
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
            ),
            new LazyFrameworkCommand(
                SchedulerRunCommand::NAME,
                'Run due BlackOps maintenance tasks once.',
                $retention->schedulerRun(...),
                $none,
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
            ),
            new LazyFrameworkCommand(
                OutboxRelayRunCommand::NAME,
                'Deliver pending transactional outbox records.',
                $factory->outboxRelayRun(...),
                static fn(Command $command): Command => $command->addOption(
                    'batches',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('until-empty', null, InputOption::VALUE_NONE),
            ),
            new LazyFrameworkCommand(
                OutboxRelayDaemonCommand::NAME,
                'Run transactional outbox delivery on an interval.',
                $factory->outboxRelayDaemon(...),
                static fn(Command $command): Command => $command->addOption(
                    'interval-milliseconds',
                    null,
                    InputOption::VALUE_REQUIRED,
                )->addOption('iterations', null, InputOption::VALUE_REQUIRED),
            ),
            new LazyFrameworkCommand(
                OutboxDeadLetterRetryCommand::NAME,
                'Retry one dead-lettered transactional outbox record.',
                $factory->outboxDeadLetterRetry(...),
                static fn(Command $command): Command => $command
                    ->addArgument('record-id', InputArgument::REQUIRED)
                    ->addOption('actor', null, InputOption::VALUE_REQUIRED)
                    ->addOption('reason', null, InputOption::VALUE_REQUIRED),
            ),
            new LazyFrameworkCommand(
                JournalObserverReplayCommand::NAME,
                'Replay canonical journal records to selected observers.',
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
        ];
    }
}
