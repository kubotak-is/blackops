CREATE SCHEMA IF NOT EXISTS blackops;

CREATE TABLE IF NOT EXISTS blackops.schema_migrations (
    version text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blackops.journal (
    record_id uuid PRIMARY KEY,
    operation_id uuid NOT NULL,
    sequence bigint NOT NULL,
    event text NOT NULL,
    attempt_id uuid NULL,
    schema_version integer NOT NULL CHECK (schema_version >= 1),
    occurred_at timestamptz NOT NULL,
    encoded_record bytea NOT NULL,
    UNIQUE (operation_id, sequence)
);

CREATE INDEX IF NOT EXISTS journal_operation_sequence_idx
    ON blackops.journal (operation_id, sequence);

CREATE INDEX IF NOT EXISTS journal_event_occurred_at_idx
    ON blackops.journal (event, occurred_at);
