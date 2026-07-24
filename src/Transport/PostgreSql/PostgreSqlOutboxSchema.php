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
                state text NOT NULL DEFAULT 'pending' CHECK (state = 'pending'),
                state_version bigint NOT NULL DEFAULT 1 CHECK (state_version = 1)
            )",
            "CREATE INDEX IF NOT EXISTS outbox_records_pending_idx
                ON {$table} (available_at, record_id)
                WHERE state = 'pending'",
        ];
    }

    public function table(): string
    {
        return $this->identifier->qualify('outbox_records');
    }
}
