<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\MigrationFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class DoctrineMigrationDependencyFactory
{
    public static function create(
        Connection $connection,
        string $schema = 'blackops',
        ?LoggerInterface $logger = null,
    ): DependencyFactory {
        $schemaName = new PostgreSqlMigrationSchema($schema);
        $migrationLogger = $logger ?? new NullLogger();

        $metadata = new TableMetadataStorageConfiguration();
        $metadata->setTableName($schemaName->doctrineTable('schema_migrations'));

        $configuration = new Configuration();
        $configuration->addMigrationsDirectory(
            'BlackOps\\Migrations\\PostgreSql',
            dirname(__DIR__, levels: 3) . '/migrations/postgresql',
        );
        $configuration->setMetadataStorageConfiguration($metadata);
        $configuration->setTransactional(true);
        $configuration->setAllOrNothing(true);

        $factory = DependencyFactory::fromConnection(
            new ExistingConfiguration($configuration),
            new ExistingConnection($connection),
            $migrationLogger,
        );
        $factory->setService(
            MigrationFactory::class,
            new ConfigurablePostgreSqlMigrationFactory($connection, $migrationLogger, $schemaName),
        );

        return $factory;
    }
}
