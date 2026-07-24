<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlJournalSchema
{
    private PostgreSqlIdentifier $identifier;
    private string $schemaName;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
        $this->schemaName = $schema;
    }

    /**
     * @return list<string>
     */
    public function statements(): array
    {
        $schema = $this->identifier->quoted();
        $migrations = $this->identifier->qualify('schema_migrations');
        $journal = $this->identifier->qualify('journal');
        $checkpoints = $this->identifier->qualify('observer_replay_checkpoints');
        $audits = $this->identifier->qualify('observer_replay_audits');

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$migrations} (
                version varchar(191) PRIMARY KEY,
                executed_at timestamp(0) without time zone NULL,
                execution_time integer NULL
            )",
            "DO \$blackops_metadata\$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = '{$this->schemaName}'
                      AND table_name = 'schema_migrations'
                      AND column_name = 'applied_at'
                ) THEN
                    ALTER TABLE {$migrations}
                        ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL;
                    UPDATE {$migrations}
                        SET executed_at = applied_at AT TIME ZONE 'UTC'
                        WHERE executed_at IS NULL;
                    ALTER TABLE {$migrations} DROP COLUMN applied_at;
                END IF;
            END
            \$blackops_metadata\$",
            "ALTER TABLE {$migrations}
                ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL",
            "ALTER TABLE {$migrations}
                ADD COLUMN IF NOT EXISTS execution_time integer NULL",
            "ALTER TABLE {$migrations}
                ALTER COLUMN version TYPE varchar(191)",
            "CREATE TABLE IF NOT EXISTS {$journal} (
                record_id uuid PRIMARY KEY,
                operation_id uuid NOT NULL,
                sequence bigint NOT NULL,
                event text NOT NULL,
                attempt_id uuid NULL,
                schema_version integer NOT NULL CHECK (schema_version >= 1),
                occurred_at timestamptz NOT NULL,
                encoded_record bytea NOT NULL,
                UNIQUE (operation_id, sequence)
            )",
            "CREATE INDEX IF NOT EXISTS journal_operation_sequence_idx
                ON {$journal} (operation_id, sequence)",
            "CREATE INDEX IF NOT EXISTS journal_event_occurred_at_idx
                ON {$journal} (event, occurred_at)",
            "CREATE TABLE IF NOT EXISTS {$checkpoints} (
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
            )",
            "CREATE TABLE IF NOT EXISTS {$audits} (
                audit_id varchar(64) PRIMARY KEY,
                checkpoint_id varchar(128) NOT NULL,
                selector_kind text NOT NULL CHECK (selector_kind IN ('operation','record','time')),
                selector_hash char(64) NOT NULL,
                target_hash char(64) NOT NULL,
                selector_operation_id uuid NULL,
                selector_record_id uuid NULL,
                selector_from timestamptz NULL,
                selector_to timestamptz NULL,
                actor text NOT NULL CHECK (actor <> ''),
                reason text NOT NULL CHECK (reason <> ''),
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
            )",
            "CREATE INDEX IF NOT EXISTS observer_replay_audits_checkpoint_idx
                ON {$audits} (checkpoint_id, started_at)",
        ];
    }

    public function journalTable(): string
    {
        return $this->identifier->qualify('journal');
    }

    public function observerReplayCheckpointTable(): string
    {
        return $this->identifier->qualify('observer_replay_checkpoints');
    }

    public function observerReplayAuditTable(): string
    {
        return $this->identifier->qualify('observer_replay_audits');
    }
}
