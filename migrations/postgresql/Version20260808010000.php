<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/** Requires protected secondary operation storage without converting legacy rows. */
final class Version20260808010000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Require BOPD envelopes for outbox, dead-letter, and idempotency projections.';
    }

    public function up(Schema $schema): void
    {
        $schemaName = $this->schemaName->name();
        $tables = ['outbox_records', 'dead_letters', 'idempotency_records'];
        foreach ($tables as $tableName) {
            $table = $this->schemaName->table($tableName);
            $this->addSql("DO \$blackops_secondary_guard\$ BEGIN
                IF to_regclass('{$schemaName}.{$tableName}') IS NOT NULL AND EXISTS (SELECT 1 FROM {$table}) THEN
                    RAISE EXCEPTION 'Protected secondary storage migration requires an empty {$tableName} table';
                END IF;
            END \$blackops_secondary_guard\$");
        }

        $outbox = $this->schemaName->table('outbox_records');
        $this->addSql("ALTER TABLE {$outbox} DROP CONSTRAINT IF EXISTS outbox_records_bopd_payload_check");
        $this->addSql("ALTER TABLE {$outbox} ADD CONSTRAINT outbox_records_bopd_payload_check CHECK (
            substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex')
            AND substring(encoded_context FROM 1 FOR 4) = decode('424f5044', 'hex')
        )");

        $dead = $this->schemaName->table('dead_letters');
        $this->addSql("ALTER TABLE {$dead} ADD COLUMN IF NOT EXISTS encoded_reason bytea NULL");
        $this->addSql("ALTER TABLE {$dead} DROP CONSTRAINT IF EXISTS dead_letters_bopd_reason_check");
        $this->addSql("ALTER TABLE {$dead} ADD CONSTRAINT dead_letters_bopd_reason_check CHECK (
            encoded_reason IS NULL OR substring(encoded_reason FROM 1 FOR 4) = decode('424f5044', 'hex')
        )");
        $this->addSql("ALTER TABLE {$dead} DROP COLUMN IF EXISTS reason_type");
        $this->addSql("ALTER TABLE {$dead} DROP COLUMN IF EXISTS reason_message");
        $this->addSql("ALTER TABLE {$dead} ALTER COLUMN encoded_reason SET NOT NULL");

        $idempotency = $this->schemaName->table('idempotency_records');
        $this->addSql("ALTER TABLE {$idempotency} ADD COLUMN IF NOT EXISTS operation_type text NULL");
        $this->addSql("ALTER TABLE {$idempotency} ADD COLUMN IF NOT EXISTS application_schema_version integer NULL");
        $this->addSql("ALTER TABLE {$idempotency} ADD COLUMN IF NOT EXISTS encoded_response bytea NULL");
        $this->addSql("ALTER TABLE {$idempotency} ADD COLUMN IF NOT EXISTS encoded_result bytea NULL");
        $this->addSql(
            "ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_response_projection_check",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_result_projection_check",
        );
        $this->addSql("ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_response_bopd_check");
        $this->addSql("ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_result_bopd_check");
        foreach ([
            'response_version',
            'response_status',
            'response_headers',
            'response_body',
            'result_kind',
            'result_type',
            'result_schema_version',
            'result_payload',
            'rejection_category',
            'rejection_code',
        ] as $column) {
            $this->addSql("ALTER TABLE {$idempotency} DROP COLUMN IF EXISTS {$column}");
        }
        $this->addSql("ALTER TABLE {$idempotency} ALTER COLUMN operation_type SET NOT NULL");
        $this->addSql("ALTER TABLE {$idempotency} ALTER COLUMN application_schema_version SET NOT NULL");
        $this->addSql("ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_operation_type_check");
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_operation_type_check CHECK (operation_type <> '')",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} DROP CONSTRAINT IF EXISTS idempotency_record_application_schema_version_check",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_application_schema_version_check CHECK (application_schema_version >= 1)",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_response_projection_check CHECK (state = 'terminal' OR (encoded_response IS NULL AND encoded_result IS NULL))",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_result_projection_check CHECK (state = 'terminal' OR (encoded_response IS NULL AND encoded_result IS NULL))",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_response_bopd_check CHECK (encoded_response IS NULL OR substring(encoded_response FROM 1 FOR 4) = decode('424f5044', 'hex'))",
        );
        $this->addSql(
            "ALTER TABLE {$idempotency} ADD CONSTRAINT idempotency_record_result_bopd_check CHECK (encoded_result IS NULL OR substring(encoded_result FROM 1 FOR 4) = decode('424f5044', 'hex'))",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Protected secondary storage cannot be downgraded to plaintext.');
    }
}
