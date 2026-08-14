<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260808100000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Add protected storage rotation checkpoints and safe audit records.';
    }

    public function up(Schema $schema): void
    {
        $checkpoints = $this->schemaName->table('storage_protection_rotation_checkpoints');
        $audits = $this->schemaName->table('storage_protection_rotation_audits');
        $this->addSql("CREATE TABLE IF NOT EXISTS {$checkpoints} (
            checkpoint_id varchar(128) PRIMARY KEY CHECK (checkpoint_id ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$'),
            scope_hash char(64) NOT NULL CHECK (scope_hash ~ '^[0-9a-f]{64}$'),
            state text NOT NULL CHECK (state IN ('running','complete','failed')),
            cursor_value text NULL,
            failure_fingerprint text NULL CHECK (failure_fingerprint IS NULL OR failure_fingerprint ~ '^v1:[0-9a-f]{64}$'),
            updated_at timestamptz NOT NULL,
            CHECK ((state = 'failed' AND failure_fingerprint IS NOT NULL) OR (state <> 'failed' AND failure_fingerprint IS NULL))
        )");
        $this->addSql("CREATE TABLE IF NOT EXISTS {$audits} (
            audit_id varchar(64) PRIMARY KEY,
            checkpoint_id varchar(128) NOT NULL,
            scope_hash char(64) NOT NULL CHECK (scope_hash ~ '^[0-9a-f]{64}$'),
            purpose text NOT NULL CHECK (purpose IN ('journal_record','deferred_payload','deferred_context','outcome_payload','outbox_payload','outbox_context','dead_letter_reason','idempotency_response','idempotency_result')),
            old_key_id varchar(128) NOT NULL CHECK (old_key_id ~ '^[A-Za-z0-9]+(?:[._:/-][A-Za-z0-9]+)*$'),
            new_key_id varchar(128) NOT NULL CHECK (new_key_id ~ '^[A-Za-z0-9]+(?:[._:/-][A-Za-z0-9]+)*$'),
            actor varchar(256) NOT NULL CHECK (btrim(actor) <> '' AND actor !~ '[[:cntrl:]]'),
            reason varchar(256) NOT NULL CHECK (btrim(reason) <> '' AND reason !~ '[[:cntrl:]]'),
            state text NOT NULL CHECK (state IN ('started','complete','failed')),
            selected_count bigint NOT NULL DEFAULT 0 CHECK (selected_count >= 0),
            rotated_count bigint NOT NULL DEFAULT 0 CHECK (rotated_count >= 0),
            skipped_count bigint NOT NULL DEFAULT 0 CHECK (skipped_count >= 0),
            failed_count bigint NOT NULL DEFAULT 0 CHECK (failed_count >= 0),
            failure_fingerprint text NULL CHECK (failure_fingerprint IS NULL OR failure_fingerprint ~ '^v1:[0-9a-f]{64}$'),
            started_at timestamptz NOT NULL,
            finished_at timestamptz NULL,
            CHECK ((state = 'started' AND finished_at IS NULL) OR (state IN ('complete','failed') AND finished_at IS NOT NULL)),
            CHECK ((state = 'failed' AND failure_fingerprint IS NOT NULL) OR (state <> 'failed' AND failure_fingerprint IS NULL)),
            CHECK (old_key_id <> new_key_id)
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS storage_protection_rotation_audits_checkpoint_idx ON {$audits} (checkpoint_id, started_at)");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Rotation audit records are retained for operational evidence.');
    }
}
