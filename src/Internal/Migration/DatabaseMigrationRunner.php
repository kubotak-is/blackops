<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use Psr\Log\LoggerInterface;

final readonly class DatabaseMigrationRunner
{
    private DependencyFactory $dependencyFactory;
    private DoctrineMigrationMetadataBootstrapper $metadataBootstrapper;

    public function __construct(Connection $connection, string $schema = 'blackops', ?LoggerInterface $logger = null)
    {
        $this->dependencyFactory = DoctrineMigrationDependencyFactory::create($connection, $schema, $logger);
        $this->metadataBootstrapper = new DoctrineMigrationMetadataBootstrapper(
            $connection,
            new PostgreSqlMigrationSchema($schema),
        );
    }

    public function dependencyFactory(): DependencyFactory
    {
        return $this->dependencyFactory;
    }

    public function status(): DatabaseMigrationStatus
    {
        $metadata = $this->dependencyFactory->getMetadataStorage();
        $applied = array_map(
            static fn(ExecutedMigration $migration): string => (string) $migration->getVersion(),
            $metadata->getExecutedMigrations()->getItems(),
        );
        $pending = array_map(
            static fn(AvailableMigration $migration): string => (string) $migration->getVersion(),
            $this->dependencyFactory->getMigrationStatusCalculator()->getNewMigrations()->getItems(),
        );

        return new DatabaseMigrationStatus(array_values($applied), array_values($pending));
    }

    public function migrate(): DatabaseMigrationResult
    {
        $this->metadataBootstrapper->initialize($this->dependencyFactory->getMetadataStorage());

        return $this->execute(
            new MigratorConfiguration()
                ->setAllOrNothing(true)
                ->setNoMigrationException(true),
        );
    }

    public function dryRun(): DatabaseMigrationResult
    {
        return $this->execute(
            new MigratorConfiguration()
                ->setDryRun(true)
                ->setAllOrNothing(true)
                ->setNoMigrationException(true),
        );
    }

    private function execute(MigratorConfiguration $configuration): DatabaseMigrationResult
    {
        $pending = $this->dependencyFactory->getMigrationStatusCalculator()->getNewMigrations()->getItems();
        $versions = array_map(static fn(AvailableMigration $migration): Version => $migration->getVersion(), $pending);
        $plan = $this->dependencyFactory->getMigrationPlanCalculator()->getPlanForVersions($versions, Direction::UP);

        $queries = $this->dependencyFactory->getMigrator()->migrate($plan, $configuration);

        $sql = [];
        foreach ($queries as $migrationQueries) {
            foreach ($migrationQueries as $query) {
                $sql[] = $query->getStatement();
            }
        }

        return new DatabaseMigrationResult($configuration->isDryRun(), count($plan), $sql);
    }
}
