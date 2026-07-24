<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260724100000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Add relay leasing, retry, fencing, and dead-letter audit persistence.';
    }

    public function up(Schema $schema): void
    {
        $table = $this->schemaName->table('outbox_records');
        $audit = $this->schemaName->table('outbox_dead_letter_retry_audits');
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS relay_id text NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS lease_expires_at timestamptz NULL");
        $this->addSql(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS fencing_token bigint NOT NULL DEFAULT 0 CHECK (fencing_token >= 0)",
        );
        $this->addSql(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0)",
        );
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS next_attempt_at timestamptz NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS failure_fingerprint text NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS failure_fingerprint_version integer NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS leased_at timestamptz NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sent_at timestamptz NULL");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS dead_lettered_at timestamptz NULL");
        $this->addSql("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_check");
        $this->addSql("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_version_check");
        $this->addSql(
            "ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_check CHECK (state IN ('pending','leased','retry_scheduled','sent','dead_lettered'))",
        );
        $this->addSql(
            "ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_version_check CHECK (state_version >= 1)",
        );
        $this->addSql(
            "CREATE INDEX IF NOT EXISTS outbox_records_claim_idx ON {$table} (COALESCE(next_attempt_at, available_at), record_id) WHERE state IN ('pending','retry_scheduled') OR (state = 'leased' AND lease_expires_at IS NOT NULL)",
        );
        $this->addSql(
            "CREATE INDEX IF NOT EXISTS outbox_records_lease_idx ON {$table} (lease_expires_at, record_id) WHERE state = 'leased'",
        );
        $this->addSql("CREATE TABLE IF NOT EXISTS {$audit} (
            audit_id uuid PRIMARY KEY,
            record_id uuid NOT NULL,
            operation_id uuid NOT NULL,
            actor text NOT NULL CHECK (actor <> ''),
            reason text NOT NULL CHECK (reason <> ''),
            retried_at timestamptz NOT NULL,
            previous_attempt_count integer NOT NULL CHECK (previous_attempt_count >= 0)
        )");
        $this->addSql(
            "CREATE INDEX IF NOT EXISTS outbox_dead_letter_retry_audits_record_idx ON {$audit} (record_id, retried_at)",
        );
    }

    public function down(Schema $schema): void
    {
        $table = $this->schemaName->table('outbox_records');
        $audit = $this->schemaName->table('outbox_dead_letter_retry_audits');
        $this->addSql(
            "UPDATE {$table} SET state='pending', state_version=1, relay_id=NULL, lease_expires_at=NULL, leased_at=NULL, next_attempt_at=NULL, failure_fingerprint=NULL, failure_fingerprint_version=NULL, sent_at=NULL, dead_lettered_at=NULL",
        );
        $this->addSql(
            "DROP INDEX IF EXISTS {$this->schemaName->quoted()}.\"outbox_dead_letter_retry_audits_record_idx\"",
        );
        $this->addSql("DROP TABLE IF EXISTS {$audit}");
        $this->addSql("DROP INDEX IF EXISTS {$this->schemaName->quoted()}.\"outbox_records_claim_idx\"");
        $this->addSql("DROP INDEX IF EXISTS {$this->schemaName->quoted()}.\"outbox_records_lease_idx\"");
        foreach ([
            'relay_id',
            'lease_expires_at',
            'fencing_token',
            'attempt_count',
            'next_attempt_at',
            'failure_fingerprint',
            'failure_fingerprint_version',
            'leased_at',
            'sent_at',
            'dead_lettered_at',
        ] as $column) {
            $this->addSql("ALTER TABLE {$table} DROP COLUMN IF EXISTS {$column}");
        }
        $this->addSql("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_check");
        $this->addSql("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_version_check");
        $this->addSql("ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_check CHECK (state = 'pending')");
        $this->addSql(
            "ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_version_check CHECK (state_version = 1)",
        );
    }
}
