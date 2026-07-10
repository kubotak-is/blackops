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
                ADD COLUMN IF NOT EXISTS created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE {$operations}
                ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP",
            "CREATE INDEX IF NOT EXISTS operations_eligible_idx
                ON {$operations} (available_at, operation_id)
                WHERE state IN ('accepted', 'retry_scheduled')",
            "CREATE INDEX IF NOT EXISTS operations_running_lease_idx
                ON {$operations} (lease_expires_at, operation_id)
                WHERE state = 'running' AND lease_expires_at IS NOT NULL",
        ];
    }

    public function operationsTable(): string
    {
        return $this->identifier->qualify('operations');
    }
}
