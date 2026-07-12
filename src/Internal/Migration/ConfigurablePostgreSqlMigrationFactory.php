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
    private const NAMESPACE_PREFIX = 'BlackOps\\Migrations\\PostgreSql\\';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private PostgreSqlMigrationSchema $schema,
    ) {}

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        if (
            !str_starts_with($migrationClassName, self::NAMESPACE_PREFIX)
            || !is_subclass_of($migrationClassName, AbstractMigration::class)
        ) {
            throw MigrationClassNotFound::new($migrationClassName);
        }

        $reflection = new ReflectionClass($migrationClassName);

        return $reflection->newInstance($this->connection, $this->logger, $this->schema->name());
    }
}
