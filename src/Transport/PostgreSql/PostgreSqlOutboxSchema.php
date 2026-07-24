<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlOutboxSchema
{
    private PostgreSqlIdentifier $identifier;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
    }

    /** @return list<string> */
    public function statements(): array
    {
        $schema = $this->identifier->quoted();
        $table = $this->table();

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$table} (
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
                state text NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','leased','retry_scheduled','sent','dead_lettered')),
                state_version bigint NOT NULL DEFAULT 1 CHECK (state_version >= 1),
                relay_id text NULL,
                lease_expires_at timestamptz NULL,
                fencing_token bigint NOT NULL DEFAULT 0 CHECK (fencing_token >= 0),
                attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
                next_attempt_at timestamptz NULL,
                failure_fingerprint text NULL,
                failure_fingerprint_version integer NULL,
                leased_at timestamptz NULL,
                sent_at timestamptz NULL,
                dead_lettered_at timestamptz NULL
            )",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS relay_id text NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS lease_expires_at timestamptz NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS fencing_token bigint NOT NULL DEFAULT 0 CHECK (fencing_token >= 0)",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0)",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS next_attempt_at timestamptz NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS failure_fingerprint text NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS failure_fingerprint_version integer NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS leased_at timestamptz NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sent_at timestamptz NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS dead_lettered_at timestamptz NULL",
            "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_check",
            "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS outbox_records_state_version_check",
            "ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_check CHECK (state IN ('pending','leased','retry_scheduled','sent','dead_lettered'))",
            "ALTER TABLE {$table} ADD CONSTRAINT outbox_records_state_version_check CHECK (state_version >= 1)",
            "CREATE INDEX IF NOT EXISTS outbox_records_claim_idx
                ON {$table} (COALESCE(next_attempt_at, available_at), record_id)
                WHERE state IN ('pending','retry_scheduled') OR (state = 'leased' AND lease_expires_at IS NOT NULL)",
            "CREATE INDEX IF NOT EXISTS outbox_records_lease_idx ON {$table} (lease_expires_at, record_id) WHERE state = 'leased'",
            "CREATE TABLE IF NOT EXISTS {$this->retryAuditTable()} (
                audit_id uuid PRIMARY KEY,
                record_id uuid NOT NULL,
                operation_id uuid NOT NULL,
                actor text NOT NULL CHECK (actor <> ''),
                reason text NOT NULL CHECK (reason <> ''),
                retried_at timestamptz NOT NULL,
                previous_attempt_count integer NOT NULL CHECK (previous_attempt_count >= 0)
            )",
            "CREATE INDEX IF NOT EXISTS outbox_dead_letter_retry_audits_record_idx ON {$this->retryAuditTable()} (record_id, retried_at)",
        ];
    }

    public function table(): string
    {
        return $this->identifier->qualify('outbox_records');
    }

    public function retryAuditTable(): string
    {
        return $this->identifier->qualify('outbox_dead_letter_retry_audits');
    }
}
