<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260712000000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);

        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Create the BlackOps PostgreSQL framework schema baseline.';
    }

    public function up(Schema $schema): void
    {
        $operations = $this->schemaName->table('operations');
        $journal = $this->schemaName->table('journal');
        $outcomes = $this->schemaName->table('outcomes');
        $deadLetters = $this->schemaName->table('dead_letters');
        $retentionHolds = $this->schemaName->table('retention_holds');
        $retentionPurgeAudits = $this->schemaName->table('retention_purge_audits');

        $this->addSql("CREATE TABLE IF NOT EXISTS {$operations} (
            operation_id uuid PRIMARY KEY,
            operation_type text NOT NULL CHECK (operation_type <> ''),
            schema_version integer NOT NULL CHECK (schema_version >= 1),
            encoded_payload bytea NULL,
            encoded_context bytea NULL,
            payload_purged_at timestamptz NULL,
            content_type text NOT NULL CHECK (content_type <> ''),
            encoding text NOT NULL CHECK (encoding <> ''),
            key_id text NULL,
            state text NOT NULL CHECK (state IN (
                'accepted',
                'running',
                'supervising',
                'retry_scheduled',
                'completed',
                'rejected',
                'failed',
                'dead_lettered'
            )),
            state_version bigint NOT NULL CHECK (state_version >= 1),
            attempt_number integer NOT NULL DEFAULT 0 CHECK (attempt_number >= 0),
            next_sequence bigint NOT NULL CHECK (next_sequence >= 1),
            available_at timestamptz NOT NULL,
            accepted_at timestamptz NOT NULL,
            lease_owner text NULL,
            lease_expires_at timestamptz NULL,
            fencing_token bigint NOT NULL DEFAULT 0 CHECK (fencing_token >= 0),
            current_attempt_id uuid NULL,
            current_attempt_started_at timestamptz NULL,
            created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT operations_payload_tombstone_check CHECK (
                (
                    encoded_payload IS NOT NULL
                    AND encoded_context IS NOT NULL
                    AND payload_purged_at IS NULL
                )
                OR (
                    state IN ('completed', 'rejected', 'failed', 'dead_lettered')
                    AND encoded_payload IS NULL
                    AND encoded_context IS NULL
                    AND payload_purged_at IS NOT NULL
                )
            )
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS operations_eligible_idx
            ON {$operations} (available_at, operation_id)
            WHERE state IN ('accepted', 'retry_scheduled')");
        $this->addSql("CREATE INDEX IF NOT EXISTS operations_running_lease_idx
            ON {$operations} (lease_expires_at, operation_id)
            WHERE state = 'running' AND lease_expires_at IS NOT NULL");

        $this->addSql("CREATE TABLE IF NOT EXISTS {$journal} (
            record_id uuid PRIMARY KEY,
            operation_id uuid NOT NULL,
            sequence bigint NOT NULL,
            event text NOT NULL,
            attempt_id uuid NULL,
            schema_version integer NOT NULL CHECK (schema_version >= 1),
            occurred_at timestamptz NOT NULL,
            encoded_record bytea NOT NULL,
            UNIQUE (operation_id, sequence)
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS journal_operation_sequence_idx
            ON {$journal} (operation_id, sequence)");
        $this->addSql("CREATE INDEX IF NOT EXISTS journal_event_occurred_at_idx
            ON {$journal} (event, occurred_at)");

        $this->addSql("CREATE TABLE IF NOT EXISTS {$outcomes} (
            operation_id uuid PRIMARY KEY,
            outcome_type text NOT NULL CHECK (outcome_type <> ''),
            schema_version integer NOT NULL CHECK (schema_version >= 1),
            encoded_payload bytea NOT NULL,
            completed_at timestamptz NOT NULL,
            CONSTRAINT outcomes_operation_id_fkey
                FOREIGN KEY (operation_id)
                REFERENCES {$operations} (operation_id)
                ON DELETE RESTRICT
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS outcomes_completed_at_idx
            ON {$outcomes} (completed_at, operation_id)");

        $this->addSql("CREATE TABLE IF NOT EXISTS {$deadLetters} (
            operation_id uuid PRIMARY KEY,
            final_attempt_id uuid NULL,
            final_attempt_number integer NULL CHECK (
                final_attempt_number IS NULL OR final_attempt_number >= 1
            ),
            reason_type text NOT NULL CHECK (reason_type <> ''),
            reason_message text NOT NULL,
            moved_at timestamptz NOT NULL,
            created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS dead_letters_moved_at_idx
            ON {$deadLetters} (moved_at, operation_id)");

        $this->addSql("CREATE TABLE IF NOT EXISTS {$retentionHolds} (
            hold_id uuid PRIMARY KEY,
            operation_id uuid NOT NULL,
            category text NOT NULL CHECK (category IN ('legal', 'security', 'audit', 'support', 'other')),
            reason text NOT NULL CHECK (reason <> ''),
            placed_at timestamptz NOT NULL,
            placed_by text NOT NULL CHECK (placed_by <> ''),
            released_at timestamptz NULL,
            released_by text NULL CHECK (released_by IS NULL OR released_by <> ''),
            created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CHECK (
                (released_at IS NULL AND released_by IS NULL)
                OR (released_at IS NOT NULL AND released_by IS NOT NULL AND released_at >= placed_at)
            ),
            CONSTRAINT retention_holds_operation_id_fkey
                FOREIGN KEY (operation_id)
                REFERENCES {$operations} (operation_id)
                ON DELETE RESTRICT
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS retention_holds_operation_active_idx
            ON {$retentionHolds} (operation_id, placed_at)
            WHERE released_at IS NULL");

        $this->addSql("CREATE TABLE IF NOT EXISTS {$retentionPurgeAudits} (
            audit_id uuid PRIMARY KEY,
            operation_id uuid NOT NULL,
            target text NOT NULL CHECK (target IN (
                'transport_payload',
                'journal',
                'outcome',
                'dead_letter'
            )),
            affected_count integer NOT NULL CHECK (affected_count >= 1),
            policy text NOT NULL CHECK (policy <> ''),
            purged_at timestamptz NOT NULL,
            purged_by text NOT NULL CHECK (purged_by <> ''),
            created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT retention_purge_audits_operation_id_fkey
                FOREIGN KEY (operation_id)
                REFERENCES {$operations} (operation_id)
                ON DELETE RESTRICT
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS retention_purge_audits_operation_idx
            ON {$retentionPurgeAudits} (operation_id, purged_at)");
        $this->addSql("CREATE INDEX IF NOT EXISTS retention_purge_audits_purged_at_idx
            ON {$retentionPurgeAudits} (purged_at, audit_id)");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The framework baseline cannot be reverted because dropping it would destroy runtime data.',
        );
    }
}
