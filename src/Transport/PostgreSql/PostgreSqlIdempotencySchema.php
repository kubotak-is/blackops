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

        $statements = [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$table} (
                scope_version integer NOT NULL CHECK (scope_version >= 1),
                scope_hash char(64) NOT NULL,
                key_version integer NOT NULL CHECK (key_version >= 1),
                key_hash char(64) NOT NULL,
                fingerprint_version integer NOT NULL CHECK (fingerprint_version >= 1),
                fingerprint_hash char(64) NOT NULL,
                operation_id uuid NOT NULL,
                operation_type text NOT NULL CHECK (operation_type <> ''),
                application_schema_version integer NOT NULL CHECK (application_schema_version >= 1),
                strategy text NOT NULL CHECK (strategy <> ''),
                state text NOT NULL CHECK (state IN ('processing', 'terminal')),
                state_version bigint NOT NULL DEFAULT 1 CHECK (state_version >= 1),
                encoded_response bytea NULL,
                encoded_result bytea NULL,
                accepted_at timestamptz NULL,
                created_at timestamptz NOT NULL,
                expires_at timestamptz NOT NULL,
                PRIMARY KEY (scope_version, scope_hash),
                CONSTRAINT idempotency_record_operation_id_unique UNIQUE (operation_id),
                CONSTRAINT idempotency_record_expiry_check CHECK (expires_at > created_at),
                CONSTRAINT idempotency_record_response_projection_check CHECK (
                    state = 'terminal' OR (encoded_response IS NULL AND encoded_result IS NULL)
                ),
                CONSTRAINT idempotency_record_result_projection_check CHECK (
                    state = 'terminal' OR (encoded_response IS NULL AND encoded_result IS NULL)
                ),
                CONSTRAINT idempotency_record_response_bopd_check CHECK (
                    encoded_response IS NULL OR substring(encoded_response FROM 1 FOR 4) = decode('424f5044', 'hex')
                ),
                CONSTRAINT idempotency_record_result_bopd_check CHECK (
                    encoded_result IS NULL OR substring(encoded_result FROM 1 FOR 4) = decode('424f5044', 'hex')
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

        return array_merge($statements, PostgreSqlTenantMetadata::alter($table, 'idempotency_records'));
    }
}
