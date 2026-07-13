<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Finder\GlobFinder;
use Doctrine\Migrations\Finder\MigrationFinder;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\MigrationFactory;
use Doctrine\Migrations\Version\Version;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

final readonly class DoctrineMigrationDependencyFactory
{
    public static function create(
        Connection $connection,
        string $schema = 'blackops',
        ?LoggerInterface $logger = null,
        ?string $applicationMigrationDirectory = null,
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
        $applicationMigrations = self::applicationMigrationDirectory($applicationMigrationDirectory);
        if ($applicationMigrations !== null) {
            $configuration->addMigrationsDirectory('App\\Migrations', $applicationMigrations);
        }
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
        $factory->setService(MigrationFinder::class, new readonly class implements MigrationFinder {
            /** @return list<class-string> */
            public function findMigrations(string $directory, ?string $namespace = null): array
            {
                return find_application_aware_migrations($directory, $namespace);
            }
        });
        $factory->setService(Comparator::class, new readonly class implements Comparator {
            public function compare(Version $a, Version $b): int
            {
                $left = (string) $a;
                $right = (string) $b;
                $namespaceOrder = migration_namespace_order($left) <=> migration_namespace_order($right);

                return $namespaceOrder !== 0 ? $namespaceOrder : strcmp($left, $right);
            }
        });

        return $factory;
    }

    private static function applicationMigrationDirectory(?string $directory): ?string
    {
        if ($directory === null) {
            return null;
        }

        if (is_link($directory)) {
            throw new InvalidArgumentException('Application migration directory must not be a symbolic link.');
        }

        if (!file_exists($directory)) {
            return null;
        }

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Application migration path must be a directory.');
        }

        $resolved = realpath($directory);
        $parent = realpath(dirname($directory));
        if ($resolved === false || $parent === false || dirname($resolved) !== $parent) {
            throw new InvalidArgumentException('Application migration directory resolves outside the application.');
        }

        return $resolved;
    }
}

function migration_namespace_order(string $version): int
{
    if (str_starts_with($version, 'BlackOps\\Migrations\\PostgreSql\\')) {
        return 0;
    }

    return str_starts_with($version, 'App\\Migrations\\') ? 1 : 2;
}

/** @return list<class-string> */
function find_application_aware_migrations(string $directory, ?string $namespace): array
{
    if ($namespace === 'BlackOps\\Migrations\\PostgreSql') {
        return array_values(new GlobFinder()->findMigrations($directory, $namespace));
    }

    if ($namespace !== 'App\\Migrations') {
        throw new InvalidArgumentException('Migration namespace is not supported.');
    }

    return find_application_migrations($directory);
}

/** @return list<class-string> */
function find_application_migrations(string $directory): array
{
    $resolved = validated_application_migration_directory($directory);
    $files = glob(rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Version*.php');
    if ($files === false) {
        $files = [];
    }

    return array_map(application_migration_class(...), $files);
}

function validated_application_migration_directory(string $directory): string
{
    if (is_link($directory)) {
        throw new InvalidArgumentException('Application migration directory must not be a symbolic link.');
    }

    $resolved = realpath($directory);
    $parent = realpath(dirname($directory));
    if ($resolved === false || $parent === false || !is_dir($resolved) || dirname($resolved) !== $parent) {
        throw new InvalidArgumentException('Application migration directory is unavailable.');
    }

    return $resolved;
}

/** @return class-string */
function application_migration_class(string $file): string
{
    $realFile = validated_application_migration_file($file);
    load_application_migration($realFile);
    $declaredClasses = get_declared_classes();
    $classes = array_values(array_filter(
        $declaredClasses,
        static fn($class): bool => new ReflectionClass($class)->getFileName() === $realFile,
    ));

    if (count($classes) !== 1) {
        throw new InvalidArgumentException('Application migration file must declare exactly one class.');
    }

    $class = $classes[0];
    $migration = new ReflectionClass($class);
    if (
        $migration->getNamespaceName() !== 'App\\Migrations'
        || $migration->getShortName() !== pathinfo($file, PATHINFO_FILENAME)
        || $migration->isAbstract()
        || !$migration->isSubclassOf(\Doctrine\Migrations\AbstractMigration::class)
    ) {
        throw new InvalidArgumentException(
            'Application migration must be a matching App\\Migrations AbstractMigration class.',
        );
    }

    return $class;
}

function validated_application_migration_file(string $file): string
{
    if (is_link($file)) {
        throw new InvalidArgumentException('Application migration file must not be a symbolic link.');
    }

    $resolved = realpath($file);
    $parent = realpath(dirname($file));
    if ($resolved === false || $parent === false || !is_file($resolved) || dirname($resolved) !== $parent) {
        throw new InvalidArgumentException('Application migration file resolves outside the application.');
    }

    return $resolved;
}

function load_application_migration(string $file): void
{
    set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);

    try {
        require_once $file;
    } finally {
        restore_error_handler();
    }
}
