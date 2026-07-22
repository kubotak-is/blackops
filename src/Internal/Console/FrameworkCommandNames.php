<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

final readonly class FrameworkCommandNames
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            ApplicationBuildCompileCommand::NAME,
            ApplicationOperationListCommand::NAME,
            MakeOperationCommand::NAME,
            MakeMigrationCommand::NAME,
            MakeAuthCommand::NAME,
            DatabaseMigrationStatusCommand::NAME,
            DatabaseMigrationMigrateCommand::NAME,
            WorkerRunCommand::NAME,
            RetentionPlanCommand::NAME,
            RetentionPurgeCommand::NAME,
            SchedulerRunCommand::NAME,
            SchedulerDaemonCommand::NAME,
            OperationInspectCommand::NAME,
            OperationViewerCommand::NAME,
            FrontendGenerateCommand::NAME,
            FrontendCheckCommand::NAME,
        ];
    }
}
