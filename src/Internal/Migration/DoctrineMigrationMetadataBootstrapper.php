<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use RuntimeException;

final readonly class DoctrineMigrationMetadataBootstrapper
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlMigrationSchema $schema,
    ) {}

    public function initialize(MetadataStorage $metadataStorage): void
    {
        $this->connection->transactional(function () use ($metadataStorage): void {
            $this->connection->executeStatement('CREATE SCHEMA IF NOT EXISTS ' . $this->schema->quoted());

            if ($this->metadataTableExists()) {
                $this->upgradeLegacyMetadataTable();
            }

            $metadataStorage->ensureInitialized();
        });
    }

    private function metadataTableExists(): bool
    {
        $exists = $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = :schema
                  AND table_name = :table
            )', [
            'schema' => $this->schema->name(),
            'table' => 'schema_migrations',
        ]);

        if (!is_bool($exists)) {
            throw new RuntimeException('PostgreSQL metadata table existence query returned an invalid value.');
        }

        return $exists;
    }

    private function upgradeLegacyMetadataTable(): void
    {
        $table = $this->schema->table('schema_migrations');
        $columns = $this->connection->fetchFirstColumn('SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = :table', [
            'schema' => $this->schema->name(),
            'table' => 'schema_migrations',
        ]);

        if (in_array('applied_at', $columns, strict: true)) {
            $this->connection->executeStatement(
                "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL",
            );
            $this->connection->executeStatement("UPDATE {$table}
                SET executed_at = applied_at AT TIME ZONE 'UTC'
                WHERE executed_at IS NULL");
            $this->connection->executeStatement("ALTER TABLE {$table} DROP COLUMN applied_at");
        }

        $this->connection->executeStatement(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL",
        );
        $this->connection->executeStatement(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS execution_time integer NULL",
        );
        $this->connection->executeStatement("ALTER TABLE {$table} ALTER COLUMN version TYPE varchar(191)");
    }
}
