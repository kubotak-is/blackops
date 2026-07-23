<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlIdempotencySchema
{
    public function __construct(
        private string $schema = 'blackops',
    ) {}

    public function table(): string
    {
        return PostgreSqlIdentifier::schema($this->schema)->qualify('idempotency_records');
    }

    /** @return list<string> */
    public function statements(): array
    {
        $schema = PostgreSqlIdentifier::schema($this->schema)->quoted();
        $table = $this->table();

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$table} (
                scope_version integer NOT NULL CHECK (scope_version >= 1),
                scope_hash char(64) NOT NULL,
                key_version integer NOT NULL CHECK (key_version >= 1),
                key_hash char(64) NOT NULL,
                fingerprint_version integer NOT NULL CHECK (fingerprint_version >= 1),
                fingerprint_hash char(64) NOT NULL,
                operation_id uuid NOT NULL,
                strategy text NOT NULL CHECK (strategy <> ''),
                state text NOT NULL CHECK (state IN ('processing', 'terminal')),
                state_version bigint NOT NULL DEFAULT 1 CHECK (state_version >= 1),
                response_version integer NULL,
                response_status integer NULL,
                response_headers jsonb NULL,
                response_body text NULL,
                result_kind text NULL CHECK (result_kind IN ('completed', 'rejected', 'internal_failure')),
                result_type text NULL,
                result_schema_version integer NULL,
                result_payload text NULL,
                rejection_category text NULL,
                rejection_code text NULL,
                accepted_at timestamptz NULL,
                created_at timestamptz NOT NULL,
                expires_at timestamptz NOT NULL,
                PRIMARY KEY (scope_version, scope_hash),
                CONSTRAINT idempotency_record_operation_id_unique UNIQUE (operation_id),
                CONSTRAINT idempotency_record_expiry_check CHECK (expires_at > created_at),
                CONSTRAINT idempotency_record_response_projection_check CHECK (
                    (response_version IS NULL AND response_status IS NULL AND response_headers IS NULL AND response_body IS NULL)
                    OR (response_version IS NOT NULL AND response_status IS NOT NULL AND response_headers IS NOT NULL AND response_body IS NOT NULL)
                ),
                CONSTRAINT idempotency_record_result_projection_check CHECK (
                    (result_kind IS NULL AND result_type IS NULL AND result_schema_version IS NULL AND result_payload IS NULL AND rejection_category IS NULL AND rejection_code IS NULL)
                    OR (result_kind = 'completed' AND result_type IS NOT NULL AND result_schema_version IS NOT NULL AND result_payload IS NOT NULL AND rejection_category IS NULL AND rejection_code IS NULL)
                    OR (result_kind = 'rejected' AND result_type IS NULL AND result_schema_version IS NULL AND result_payload IS NULL AND rejection_category IS NOT NULL AND rejection_code IS NOT NULL)
                    OR (result_kind = 'internal_failure' AND result_type IS NULL AND result_schema_version IS NULL AND result_payload IS NULL AND rejection_category IS NULL AND rejection_code IS NULL)
                ),
                CONSTRAINT idempotency_record_accepted_at_check CHECK (
                    (state = 'processing' AND accepted_at IS NULL)
                    OR (state = 'terminal' AND (
                        (strategy = 'BlackOps\\Core\\Execution\\Deferred' AND accepted_at IS NOT NULL)
                        OR (strategy <> 'BlackOps\\Core\\Execution\\Deferred' AND accepted_at IS NULL)
                    ))
                )
            )",
            "CREATE INDEX IF NOT EXISTS idempotency_records_expiry_idx
                ON {$table} (expires_at, scope_version, scope_hash)
                WHERE state = 'terminal'",
        ];
    }
}
