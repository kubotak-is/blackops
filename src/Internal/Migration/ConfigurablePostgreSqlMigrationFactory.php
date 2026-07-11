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
    private const BASELINE = 'BlackOps\\Migrations\\PostgreSql\\Version20260712000000';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private PostgreSqlMigrationSchema $schema,
    ) {}

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        if ($migrationClassName !== self::BASELINE || !is_subclass_of($migrationClassName, AbstractMigration::class)) {
            throw MigrationClassNotFound::new($migrationClassName);
        }

        $reflection = new ReflectionClass($migrationClassName);

        return $reflection->newInstance($this->connection, $this->logger, $this->schema->name());
    }
}
