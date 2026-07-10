<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlDeferredOperationSchema
{
    private PostgreSqlIdentifier $identifier;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
    }

    /**
     * @return list<string>
     */
    public function statements(): array
    {
        $schema = $this->identifier->quoted();
        $operations = $this->identifier->qualify('operations');
        $deadLetters = $this->identifier->qualify('dead_letters');

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$operations} (
                operation_id uuid PRIMARY KEY,
                operation_type text NOT NULL CHECK (operation_type <> ''),
                schema_version integer NOT NULL CHECK (schema_version >= 1),
                encoded_payload bytea NOT NULL,
                encoded_context bytea NOT NULL,
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
                updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS attempt_number integer NOT NULL DEFAULT 0 CHECK (attempt_number >= 0)",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS lease_owner text NULL",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS lease_expires_at timestamptz NULL",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS fencing_token bigint NOT NULL DEFAULT 0 CHECK (fencing_token >= 0)",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS current_attempt_id uuid NULL",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS current_attempt_started_at timestamptz NULL",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE {$operations}
                DROP CONSTRAINT IF EXISTS operations_state_check",
            "ALTER TABLE {$operations}
                ADD CONSTRAINT operations_state_check CHECK (state IN (
                    'accepted',
                    'running',
                    'supervising',
                    'retry_scheduled',
                    'completed',
                    'rejected',
                    'failed',
                    'dead_lettered'
                ))",
            "CREATE INDEX IF NOT EXISTS operations_eligible_idx
                ON {$operations} (available_at, operation_id)
                WHERE state IN ('accepted', 'retry_scheduled')",
            "CREATE INDEX IF NOT EXISTS operations_running_lease_idx
                ON {$operations} (lease_expires_at, operation_id)
                WHERE state = 'running' AND lease_expires_at IS NOT NULL",
            "CREATE TABLE IF NOT EXISTS {$deadLetters} (
                operation_id uuid PRIMARY KEY,
                final_attempt_id uuid NULL,
                final_attempt_number integer NULL CHECK (
                    final_attempt_number IS NULL OR final_attempt_number >= 1
                ),
                reason_type text NOT NULL CHECK (reason_type <> ''),
                reason_message text NOT NULL,
                moved_at timestamptz NOT NULL,
                created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS dead_letters_moved_at_idx
                ON {$deadLetters} (moved_at, operation_id)",
        ];
    }

    public function operationsTable(): string
    {
        return $this->identifier->qualify('operations');
    }

    public function deadLettersTable(): string
    {
        return $this->identifier->qualify('dead_letters');
    }
}
