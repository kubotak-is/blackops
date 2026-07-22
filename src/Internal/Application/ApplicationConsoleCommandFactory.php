<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Console\FrontendCheckCommand;
use BlackOps\Internal\Console\FrontendGenerateCommand;
use BlackOps\Internal\Console\MakeAuthCommand;
use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Console\OperationInspectCommand;
use BlackOps\Internal\Console\OperationViewerCommand;
use BlackOps\Internal\Console\WorkerRunCommand;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRouter;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use BlackOps\Internal\Generator\AuthGenerator;
use BlackOps\Internal\Generator\MigrationGenerator;
use BlackOps\Internal\Generator\OperationGenerator;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Symfony\Component\Console\Command\Command;

/** @mago-expect lint:too-many-methods */
final class ApplicationConsoleCommandFactory
{
    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {}

    public function build(): Command
    {
        return new ApplicationBuildCompileCommand($this->configuration);
    }

    public function operations(): Command
    {
        return new ApplicationOperationListCommand($this->configuration);
    }

    public function makeOperation(): Command
    {
        return new MakeOperationCommand(
            new OperationGenerator($this->configuration->basePath(), dirname(__DIR__, levels: 3) . '/resources/stubs'),
        );
    }

    public function makeMigration(): Command
    {
        return new MakeMigrationCommand(
            new MigrationGenerator($this->configuration->basePath(), dirname(__DIR__, levels: 3) . '/resources/stubs'),
        );
    }

    public function makeAuth(): Command
    {
        return new MakeAuthCommand(
            new AuthGenerator($this->configuration->basePath(), dirname(__DIR__, levels: 3) . '/resources/stubs'),
        );
    }

    public function frontendGenerate(): Command
    {
        return new FrontendGenerateCommand($this->configuration);
    }

    public function frontendCheck(): Command
    {
        return new FrontendCheckCommand($this->configuration);
    }

    public function databaseStatus(): Command
    {
        return new DatabaseMigrationStatusCommand($this->migrationRunner());
    }

    public function databaseMigrate(): Command
    {
        return new DatabaseMigrationMigrateCommand($this->migrationRunner());
    }

    public function worker(): Command
    {
        return new WorkerRunCommand(new ApplicationWorkerComposer()->compose($this->configuration)->loop);
    }

    public function operationInspect(): Command
    {
        return new OperationInspectCommand(
            fn(OperationId $operationId): OperationDiagnosticsResult => new ApplicationDiagnosticsQueryFactory($this->configuration)
                ->create()
                ->find($operationId),
        );
    }

    public function operationViewer(): Command
    {
        return new OperationViewerCommand(
            fn(): ApplicationDiagnosticsViewerConfiguration => ApplicationDiagnosticsViewerConfiguration::fromConfiguration(
                $this->configuration->configuration(),
            ),
            fn(
                ApplicationDiagnosticsViewerConfiguration $configuration,
                OperationViewerTokens $tokens,
            ): OperationViewerRouter => new OperationViewerRouter(
                $configuration->authority(),
                $tokens,
                fn(OperationId $operationId): OperationDiagnosticsResult => new ApplicationDiagnosticsQueryFactory($this->configuration)
                    ->create()
                    ->find($operationId),
            ),
        );
    }

    private function migrationRunner(): DatabaseMigrationRunner
    {
        $database = ApplicationDatabaseConfiguration::fromConfiguration($this->configuration->configuration());
        $connection = $database->databaseManager()->connection($database->frameworkConnection);

        return new DatabaseMigrationRunner(
            $connection,
            $database->schema,
            applicationMigrationDirectory: $this->configuration->basePath() . '/migrations',
        );
    }
}
