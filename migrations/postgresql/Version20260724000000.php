<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260724000000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Create the PostgreSQL idempotency record store.';
    }

    public function up(Schema $schema): void
    {
        $table = $this->schemaName->table('idempotency_records');
        $this->addSql("CREATE TABLE IF NOT EXISTS {$table} (
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
        )");
        $this->addSql("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS accepted_at timestamptz NULL");
        $this->addSql("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS idempotency_record_accepted_at_check");
        $this->addSql("ALTER TABLE {$table} ADD CONSTRAINT idempotency_record_accepted_at_check CHECK (
            (state = 'processing' AND accepted_at IS NULL)
            OR (state = 'terminal' AND (
                (strategy = 'BlackOps\\Core\\Execution\\Deferred' AND accepted_at IS NOT NULL)
                OR (strategy <> 'BlackOps\\Core\\Execution\\Deferred' AND accepted_at IS NULL)
            ))
        )");
        $this->addSql("CREATE INDEX IF NOT EXISTS idempotency_records_expiry_idx
            ON {$table} (expires_at, scope_version, scope_hash) WHERE state = 'terminal'");
        $audits = $this->schemaName->table('retention_purge_audits');
        $this->addSql("ALTER TABLE {$audits} DROP CONSTRAINT IF EXISTS retention_purge_audits_target_check");
        $this->addSql("ALTER TABLE {$audits} ADD CONSTRAINT retention_purge_audits_target_check CHECK (target IN (
            'transport_payload', 'journal', 'outcome', 'dead_letter', 'idempotency_record'
        ))");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Idempotency records are retained for audit safety.');
    }
}
