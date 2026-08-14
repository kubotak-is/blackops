<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/** Introduces authenticated protection for operation-owned payloads. */
final class Version20260808000000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Require BOPD envelopes for canonical journal, deferred payloads, and outcomes.';
    }

    public function up(Schema $schema): void
    {
        $journal = $this->schemaName->table('journal');
        $operations = $this->schemaName->table('operations');
        $outcomes = $this->schemaName->table('outcomes');
        $schemaName = $this->schemaName->name();

        $this->addSql("DO \$blackops_protection_guard\$ BEGIN
            IF to_regclass('{$schemaName}.journal') IS NOT NULL AND EXISTS (SELECT 1 FROM {$journal}) THEN
                RAISE EXCEPTION 'Protected storage migration requires an empty journal table';
            END IF;
            IF to_regclass('{$schemaName}.operations') IS NOT NULL AND EXISTS (SELECT 1 FROM {$operations}) THEN
                RAISE EXCEPTION 'Protected storage migration requires an empty operations table';
            END IF;
            IF to_regclass('{$schemaName}.outcomes') IS NOT NULL AND EXISTS (SELECT 1 FROM {$outcomes}) THEN
                RAISE EXCEPTION 'Protected storage migration requires an empty outcomes table';
            END IF;
        END \$blackops_protection_guard\$");

        $this->addSql("ALTER TABLE {$journal} ADD COLUMN IF NOT EXISTS operation_schema_version integer NOT NULL");
        $this->addSql("ALTER TABLE {$journal} ALTER COLUMN operation_schema_version SET NOT NULL");
        $this->addSql("ALTER TABLE {$journal} DROP CONSTRAINT IF EXISTS journal_operation_schema_version_check");
        $this->addSql(
            "ALTER TABLE {$journal} ADD CONSTRAINT journal_operation_schema_version_check CHECK (operation_schema_version >= 1)",
        );
        $this->addSql("ALTER TABLE {$journal} DROP CONSTRAINT IF EXISTS journal_bopd_envelope_check");
        $this->addSql(
            "ALTER TABLE {$journal} ADD CONSTRAINT journal_bopd_envelope_check CHECK (substring(encoded_record FROM 1 FOR 4) = decode('424f5044', 'hex'))",
        );
        $this->addSql("ALTER TABLE {$operations} DROP CONSTRAINT IF EXISTS operations_bopd_payload_check");
        $this->addSql(
            "ALTER TABLE {$operations} ADD CONSTRAINT operations_bopd_payload_check CHECK ((encoded_payload IS NULL OR substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex')) AND (encoded_context IS NULL OR substring(encoded_context FROM 1 FOR 4) = decode('424f5044', 'hex')))",
        );
        $this->addSql("ALTER TABLE {$outcomes} DROP CONSTRAINT IF EXISTS outcomes_bopd_payload_check");
        $this->addSql(
            "ALTER TABLE {$outcomes} ADD CONSTRAINT outcomes_bopd_payload_check CHECK (substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex'))",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Protected storage cannot be downgraded to plaintext.');
    }
}
