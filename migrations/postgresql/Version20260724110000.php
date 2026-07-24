<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260724110000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Add canonical observer replay checkpoints and audit records.';
    }

    public function up(Schema $schema): void
    {
        $checkpoints = $this->schemaName->table('observer_replay_checkpoints');
        $audits = $this->schemaName->table('observer_replay_audits');
        $this->addSql("CREATE TABLE IF NOT EXISTS {$checkpoints} (
            checkpoint_id varchar(128) PRIMARY KEY CHECK (checkpoint_id ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$'),
            selector_hash char(64) NOT NULL,
            target_hash char(64) NOT NULL,
            selector_kind text NOT NULL CHECK (selector_kind IN ('operation','record','time')),
            selector_operation_id uuid NULL,
            selector_record_id uuid NULL,
            selector_from timestamptz NULL,
            selector_to timestamptz NULL,
            target_names jsonb NOT NULL CHECK (jsonb_typeof(target_names) = 'array' AND jsonb_array_length(target_names) > 0),
            first_record_id uuid NULL,
            last_record_id uuid NULL,
            state text NOT NULL CHECK (state IN ('running','paused','failed','complete')),
            cursor_record_id text NULL,
            selected_count bigint NOT NULL DEFAULT 0 CHECK (selected_count >= 0),
            delivered_count bigint NOT NULL DEFAULT 0 CHECK (delivered_count >= 0),
            failure_fingerprint text NULL,
            updated_at timestamptz NOT NULL,
            CHECK ((selector_kind = 'operation' AND selector_operation_id IS NOT NULL AND selector_record_id IS NULL AND selector_from IS NULL AND selector_to IS NULL)
                OR (selector_kind = 'record' AND selector_operation_id IS NULL AND selector_record_id IS NOT NULL AND selector_from IS NULL AND selector_to IS NULL)
                OR (selector_kind = 'time' AND selector_operation_id IS NULL AND selector_record_id IS NULL AND selector_from IS NOT NULL AND selector_to IS NOT NULL AND selector_from < selector_to)),
            CHECK (selector_hash ~ '^[0-9a-f]{64}$' AND target_hash ~ '^[0-9a-f]{64}$'),
            CHECK (failure_fingerprint IS NULL OR failure_fingerprint ~ '^v1:[0-9a-f]{64}$')
        )");
        $this->addSql("CREATE TABLE IF NOT EXISTS {$audits} (
            audit_id varchar(64) PRIMARY KEY,
            checkpoint_id varchar(128) NOT NULL,
            selector_kind text NOT NULL CHECK (selector_kind IN ('operation','record','time')),
            selector_hash char(64) NOT NULL,
            target_hash char(64) NOT NULL,
            actor text NOT NULL CHECK (actor <> ''),
            reason text NOT NULL CHECK (reason <> ''),
            selector_operation_id uuid NULL,
            selector_record_id uuid NULL,
            selector_from timestamptz NULL,
            selector_to timestamptz NULL,
            target_names jsonb NOT NULL CHECK (jsonb_typeof(target_names) = 'array' AND jsonb_array_length(target_names) > 0),
            state text NOT NULL CHECK (state IN ('started','failed','complete')),
            failure_fingerprint text NULL,
            started_at timestamptz NOT NULL,
            finished_at timestamptz NULL,
            selected_count bigint NOT NULL DEFAULT 0 CHECK (selected_count >= 0),
            delivered_count bigint NOT NULL DEFAULT 0 CHECK (delivered_count >= 0),
            first_record_id uuid NULL,
            last_record_id uuid NULL,
            CHECK ((selector_kind = 'operation' AND selector_operation_id IS NOT NULL AND selector_record_id IS NULL AND selector_from IS NULL AND selector_to IS NULL)
                OR (selector_kind = 'record' AND selector_operation_id IS NULL AND selector_record_id IS NOT NULL AND selector_from IS NULL AND selector_to IS NULL)
                OR (selector_kind = 'time' AND selector_operation_id IS NULL AND selector_record_id IS NULL AND selector_from IS NOT NULL AND selector_to IS NOT NULL AND selector_from < selector_to)),
            CHECK (selector_hash ~ '^[0-9a-f]{64}$' AND target_hash ~ '^[0-9a-f]{64}$'),
            CHECK (failure_fingerprint IS NULL OR failure_fingerprint ~ '^v1:[0-9a-f]{64}$')
        )");
        $this->addSql(
            "CREATE INDEX IF NOT EXISTS observer_replay_audits_checkpoint_idx ON {$audits} (checkpoint_id, started_at)",
        );
    }

    public function down(Schema $schema): void
    {
        $audits = $this->schemaName->table('observer_replay_audits');
        $checkpoints = $this->schemaName->table('observer_replay_checkpoints');
        $this->addSql("DROP INDEX IF EXISTS {$this->schemaName->quoted()}.\"observer_replay_audits_checkpoint_idx\"");
        $this->addSql("DROP TABLE IF EXISTS {$audits}");
        $this->addSql("DROP TABLE IF EXISTS {$checkpoints}");
    }
}
