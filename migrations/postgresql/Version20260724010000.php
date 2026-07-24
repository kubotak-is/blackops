<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260724010000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Create the transactional outbox persistence table.';
    }

    public function up(Schema $schema): void
    {
        $table = $this->schemaName->table('outbox_records');
        $this->addSql("CREATE TABLE IF NOT EXISTS {$table} (
            record_id uuid PRIMARY KEY,
            operation_id uuid NOT NULL UNIQUE,
            operation_type text NOT NULL CHECK (operation_type <> ''),
            schema_version integer NOT NULL CHECK (schema_version >= 1),
            encoded_payload bytea NOT NULL,
            encoded_context bytea NOT NULL,
            content_type text NOT NULL CHECK (content_type <> ''),
            encoding text NOT NULL CHECK (encoding <> ''),
            key_id text NULL,
            available_at timestamptz NOT NULL,
            recorded_at timestamptz NOT NULL,
            connection_name text NOT NULL CHECK (connection_name <> ''),
            state text NOT NULL DEFAULT 'pending' CHECK (state = 'pending'),
            state_version bigint NOT NULL DEFAULT 1 CHECK (state_version = 1)
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS outbox_records_pending_idx
            ON {$table} (available_at, record_id) WHERE state = 'pending'");
    }

    public function down(Schema $schema): void
    {
        $table = $this->schemaName->table('outbox_records');
        $this->addSql("DROP INDEX IF EXISTS {$this->schemaName->quoted()}.\"outbox_records_pending_idx\"");
        $this->addSql("DROP TABLE IF EXISTS {$table}");
    }
}
