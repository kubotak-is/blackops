<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

use BlackOps\Internal\Console\AboutCommand;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Console\DatabaseSeedCommand;
use BlackOps\Internal\Console\DiagnosticsCheckCommand;
use BlackOps\Internal\Console\FrontendCheckCommand;
use BlackOps\Internal\Console\FrontendGenerateCommand;
use BlackOps\Internal\Console\JournalObserverReplayCommand;
use BlackOps\Internal\Console\MakeAuthCommand;
use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Console\MakeSeederCommand;
use BlackOps\Internal\Console\OperationInspectCommand;
use BlackOps\Internal\Console\OperationViewerCommand;
use BlackOps\Internal\Console\OutboxDeadLetterRetryCommand;
use BlackOps\Internal\Console\OutboxRelayDaemonCommand;
use BlackOps\Internal\Console\OutboxRelayRunCommand;
use BlackOps\Internal\Console\OutboxStatusCommand;
use BlackOps\Internal\Console\QueueStatusCommand;
use BlackOps\Internal\Console\RetentionPlanCommand;
use BlackOps\Internal\Console\RetentionPurgeCommand;
use BlackOps\Internal\Console\ScheduledOperationRunCommand;
use BlackOps\Internal\Console\SchedulerDaemonCommand;
use BlackOps\Internal\Console\SchedulerRunCommand;
use BlackOps\Internal\Console\StorageProtectionPlanCommand;
use BlackOps\Internal\Console\StorageProtectionRotateCommand;
use BlackOps\Internal\Console\WorkerRunCommand;

final class CommandProjectionTargetRegistry
{
    /**
     * @return list<CommandProjectionTarget>
     */
    public static function framework(): array
    {
        return [
            self::makeTarget(
                ApplicationBuildCompileCommand::NAME,
                'Compile application operation, HTTP, command, and container artifacts.',
            ),
            self::makeTarget(
                ApplicationOperationListCommand::NAME,
                'List operation metadata from application providers.',
            ),
            self::makeTarget(MakeOperationCommand::NAME, 'Generate a typed self-handled operation.'),
            self::makeTarget(MakeMigrationCommand::NAME, 'Generate an application database migration.'),
            self::makeTarget(MakeSeederCommand::NAME, 'Generate an application database seeder.'),
            self::makeTarget(MakeAuthCommand::NAME, 'Generate a bearer session authentication starter.'),
            self::makeTarget(
                DatabaseMigrationStatusCommand::NAME,
                'Show applied and pending BlackOps database migrations.',
            ),
            self::makeTarget(DatabaseMigrationMigrateCommand::NAME, 'Apply or preview BlackOps database migrations.'),
            self::makeTarget(DatabaseSeedCommand::NAME, 'Run the compiled application database root seeder.'),
            self::makeTarget(WorkerRunCommand::NAME, 'Run the deferred operation worker loop.'),
            self::makeTarget(ScheduledOperationRunCommand::NAME, 'Evaluate and invoke application schedules once.'),
            self::makeTarget(RetentionPlanCommand::NAME, 'Build and print a retention purge plan without applying it.'),
            self::makeTarget(RetentionPurgeCommand::NAME, 'Dry-run or apply retention purge.'),
            self::makeTarget(SchedulerRunCommand::NAME, 'Run due BlackOps maintenance tasks once.'),
            self::makeTarget(SchedulerDaemonCommand::NAME, 'Run BlackOps maintenance tasks on an interval.'),
            self::makeTarget(OutboxRelayRunCommand::NAME, 'Deliver pending transactional outbox records.'),
            self::makeTarget(OutboxRelayDaemonCommand::NAME, 'Run transactional outbox delivery on an interval.'),
            self::makeTarget(
                OutboxDeadLetterRetryCommand::NAME,
                'Retry one dead-lettered transactional outbox record.',
            ),
            self::makeTarget(OperationInspectCommand::NAME, 'Inspect one operation lifecycle and outcome.'),
            self::makeTarget(OperationViewerCommand::NAME, 'Start the read-only local operation diagnostics viewer.'),
            self::makeTarget(
                FrontendGenerateCommand::NAME,
                'Generate TypeScript operation objects from the frontend contract.',
            ),
            self::makeTarget(FrontendCheckCommand::NAME, 'Check generated TypeScript operation objects for drift.'),
            self::makeTarget(
                JournalObserverReplayCommand::NAME,
                'Replay canonical journal records to selected observers.',
            ),
            self::makeTarget(
                StorageProtectionPlanCommand::NAME,
                'Plan a bounded protected storage key rotation without writing data.',
            ),
            self::makeTarget(
                StorageProtectionRotateCommand::NAME,
                'Dry-run or apply a bounded protected storage key rotation.',
            ),
            self::makeTarget(DiagnosticsCheckCommand::NAME, 'Check application readiness and compiled artifacts.'),
            self::makeTarget(QueueStatusCommand::NAME, 'Show aggregate deferred-operation queue status.'),
            self::makeTarget(AboutCommand::NAME, 'Show installed BlackOps package, runtime, and projection status.'),
            self::makeTarget(OutboxStatusCommand::NAME, 'Show aggregate transactional outbox status.'),
        ];
    }

    /** @return list<CommandProjectionTarget> */
    public static function defaults(): array
    {
        return [
            self::makeTarget('list', 'List commands', aliases: ['ls']),
            self::makeTarget('help', 'Display help for a command'),
            self::makeTarget('completion', 'Dump the shell completion script'),
            self::makeTarget('_complete', 'Internal command to provide shell completion suggestions', hidden: true),
        ];
    }

    /** @return list<CommandProjectionTarget> */
    public static function all(): array
    {
        return [...self::framework(), ...self::defaults(), self::routeTarget(), self::scheduleTarget()];
    }

    /** @return list<string> */
    public static function frameworkNames(): array
    {
        return array_map(static fn(CommandProjectionTarget $target): string => $target->name, self::framework());
    }

    public static function target(string $name): CommandProjectionTarget
    {
        foreach (self::all() as $target) {
            if ($target->name === $name) {
                return $target;
            }
        }

        throw new \InvalidArgumentException('Unknown command projection target: ' . $name);
    }

    public static function description(string $name): string
    {
        return self::target($name)->description;
    }

    private static function routeTarget(): CommandProjectionTarget
    {
        return self::makeTarget('route:list', 'List compiled HTTP routes.', aliases: ['route:ls']);
    }

    private static function scheduleTarget(): CommandProjectionTarget
    {
        return self::makeTarget('schedule:list', 'List compiled application schedules.', aliases: ['schedule:ls']);
    }

    /** @param list<string> $aliases */
    private static function makeTarget(
        string $name,
        string $description,
        array $aliases = [],
        bool $hidden = false,
    ): CommandProjectionTarget {
        return new CommandProjectionTarget($name, $aliases, $description, $hidden);
    }
}
