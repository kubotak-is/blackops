<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/** Adds restricted clear tenant and origin-actor subjects without backfilling legacy rows. */
final class Version20260803000000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Add restricted tenant and origin actor metadata to operation-owned rows.';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            ['name' => 'operations', 'identity' => 'operation_id', 'origin' => true],
            ['name' => 'journal', 'identity' => 'operation_id', 'origin' => true],
            ['name' => 'outcomes', 'identity' => 'operation_id', 'origin' => false],
            ['name' => 'idempotency_records', 'identity' => 'operation_id', 'origin' => false],
            ['name' => 'outbox_records', 'identity' => 'operation_id', 'origin' => true],
            ['name' => 'dead_letters', 'identity' => 'operation_id', 'origin' => false],
            ['name' => 'retention_holds', 'identity' => 'operation_id', 'origin' => false],
            ['name' => 'retention_purge_audits', 'identity' => 'operation_id', 'origin' => false],
            ['name' => 'schedule_occurrences', 'identity' => 'operation_id', 'origin' => false],
        ] as $table) {
            $qualified = $this->schemaName->table($table['name']);
            $regclass = $this->schemaName->name() . '.' . $table['name'];
            $required = ['tenant_type', 'tenant_id'];
            if ($table['origin']) {
                $required[] = 'origin_actor_type';
                $required[] = 'origin_actor_id';
            }
            if ($table['name'] === 'journal') {
                $required[] = 'operation_type';
            }
            $missing = implode(' OR ', array_map(
                static fn(string $column): string => "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$regclass}') AND attname = '{$column}' AND NOT attisdropped)",
                $required,
            ));
            $this->addSql("DO \$blackops_tenant_guard\$ BEGIN
                IF to_regclass('{$regclass}') IS NOT NULL
                    AND EXISTS (SELECT 1 FROM {$qualified} LIMIT 1)
                    AND ({$missing}) THEN
                    RAISE EXCEPTION 'Tenant metadata migration requires an empty legacy table: {$table['name']}';
                END IF;
            END \$blackops_tenant_guard\$");
            $this->addSql("ALTER TABLE {$qualified} ADD COLUMN IF NOT EXISTS tenant_type text NULL");
            $this->addSql("ALTER TABLE {$qualified} ADD COLUMN IF NOT EXISTS tenant_id text NULL");
            if ($table['name'] === 'journal') {
                $this->addSql(
                    "ALTER TABLE {$qualified} ADD COLUMN IF NOT EXISTS operation_type text NOT NULL CHECK (operation_type <> '')",
                );
            }
            $this->addSql("ALTER TABLE {$qualified} DROP CONSTRAINT IF EXISTS {$table['name']}_tenant_pair_check");
            $this->addSql(
                "ALTER TABLE {$qualified} ADD CONSTRAINT {$table['name']}_tenant_pair_check CHECK (((tenant_type IS NULL) = (tenant_id IS NULL)) AND (tenant_type IS NULL OR (tenant_type <> '' AND tenant_id <> '')))",
            );
            $this->addSql(
                "CREATE INDEX IF NOT EXISTS {$table['name']}_tenant_identity_idx ON {$qualified} (tenant_type, tenant_id, {$table['identity']})",
            );
            if ($table['origin']) {
                $this->addSql("ALTER TABLE {$qualified} ADD COLUMN IF NOT EXISTS origin_actor_type text NULL");
                $this->addSql("ALTER TABLE {$qualified} ADD COLUMN IF NOT EXISTS origin_actor_id text NULL");
                $this->addSql(
                    "ALTER TABLE {$qualified} DROP CONSTRAINT IF EXISTS {$table['name']}_origin_actor_pair_check",
                );
                $this->addSql(
                    "ALTER TABLE {$qualified} ADD CONSTRAINT {$table['name']}_origin_actor_pair_check CHECK (((origin_actor_type IS NULL) = (origin_actor_id IS NULL)) AND (origin_actor_type IS NULL OR (origin_actor_type <> '' AND origin_actor_id <> '')))",
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Tenant metadata is retained for isolation safety.');
    }
}
