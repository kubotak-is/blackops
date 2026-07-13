<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Console\WorkerRunCommand;
use BlackOps\Internal\Generator\MigrationGenerator;
use BlackOps\Internal\Generator\OperationGenerator;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Command\Command;

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

    private function migrationRunner(): DatabaseMigrationRunner
    {
        $database = ApplicationDatabaseConfiguration::fromConfiguration($this->configuration->configuration());

        return new DatabaseMigrationRunner(
            $this->connection($database->connection),
            $database->schema,
            applicationMigrationDirectory: $this->configuration->basePath() . '/migrations',
        );
    }

    /** @param array<string, mixed> $parameters */
    private function connection(array $parameters): Connection
    {
        /** @var callable(array<string, mixed>): Connection $factory */
        $factory = [DriverManager::class, 'getConnection'];

        return $factory($parameters);
    }
}
