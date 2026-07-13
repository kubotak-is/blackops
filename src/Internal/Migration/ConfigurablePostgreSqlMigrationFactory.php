<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Version\MigrationFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final readonly class ConfigurablePostgreSqlMigrationFactory implements MigrationFactory
{
    private const FRAMEWORK_NAMESPACE = 'BlackOps\\Migrations\\PostgreSql';
    private const APPLICATION_NAMESPACE = 'App\\Migrations';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private PostgreSqlMigrationSchema $schema,
    ) {}

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        if (!is_subclass_of($migrationClassName, AbstractMigration::class)) {
            throw MigrationClassNotFound::new($migrationClassName);
        }

        $reflection = new ReflectionClass($migrationClassName);
        if ($reflection->isAbstract()) {
            throw MigrationClassNotFound::new($migrationClassName);
        }

        return match ($reflection->getNamespaceName()) {
            self::FRAMEWORK_NAMESPACE => $reflection->newInstance(
                $this->connection,
                $this->logger,
                $this->schema->name(),
            ),
            self::APPLICATION_NAMESPACE => $reflection->newInstance($this->connection, $this->logger),
            default => throw MigrationClassNotFound::new($migrationClassName),
        };
    }
}
