<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

/**
 * Shared SQL fragments for the restricted, clear tenant subject carried by
 * operation-owned rows. A missing tenant is a deliberate global operation;
 * it is never inferred from an encoded payload.
 */
final class PostgreSqlTenantMetadata
{
    public static function columns(bool $originActor = false): string
    {
        $columns = 'tenant_type text NULL, tenant_id text NULL';
        if ($originActor) {
            $columns .= ', origin_actor_type text NULL, origin_actor_id text NULL';
        }

        return $columns;
    }

    public static function constraint(string $table, bool $originActor = false): string
    {
        $constraint = "CONSTRAINT {$table}_tenant_pair_check CHECK (((tenant_type IS NULL) = (tenant_id IS NULL)) AND (tenant_type IS NULL OR (tenant_type <> '' AND tenant_id <> '')))";
        if ($originActor) {
            $constraint .= ", CONSTRAINT {$table}_origin_actor_pair_check CHECK (((origin_actor_type IS NULL) = (origin_actor_id IS NULL)) AND (origin_actor_type IS NULL OR (origin_actor_type <> '' AND origin_actor_id <> '')))";
        }

        return $constraint;
    }

    /** @return list<string> */
    public static function alter(
        string $table,
        string $name,
        bool $originActor = false,
        string $identityColumn = 'operation_id',
    ): array {
        $missingColumns = [
            "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$table}') AND attname = 'tenant_type' AND NOT attisdropped)",
            "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$table}') AND attname = 'tenant_id' AND NOT attisdropped)",
        ];
        if ($originActor) {
            $missingColumns[] = "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$table}') AND attname = 'origin_actor_type' AND NOT attisdropped)";
            $missingColumns[] = "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$table}') AND attname = 'origin_actor_id' AND NOT attisdropped)";
        }
        if ($name === 'journal') {
            $missingColumns[] = "NOT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('{$table}') AND attname = 'operation_type' AND NOT attisdropped)";
        }
        $missingGuard = implode(' OR ', $missingColumns);
        $statements = [
            "DO \$blackops_tenant_guard\$ BEGIN IF to_regclass('{$table}') IS NOT NULL
                AND EXISTS (SELECT 1 FROM {$table} LIMIT 1)
                AND ({$missingGuard}) THEN RAISE EXCEPTION 'Tenant metadata migration requires an empty legacy table: {$name}'; END IF; END \$blackops_tenant_guard\$",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS tenant_type text NULL",
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS tenant_id text NULL",
            ...(
                $name === 'journal'
                    ? [
                        "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS operation_type text NOT NULL CHECK (operation_type <> '')",
                    ] : []
            ),
            "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}_tenant_pair_check",
            "ALTER TABLE {$table} ADD CONSTRAINT {$name}_tenant_pair_check CHECK (((tenant_type IS NULL) = (tenant_id IS NULL)) AND (tenant_type IS NULL OR (tenant_type <> '' AND tenant_id <> '')))",
            "CREATE INDEX IF NOT EXISTS {$name}_tenant_identity_idx ON {$table} (tenant_type, tenant_id, {$identityColumn})",
        ];
        if ($originActor) {
            $statements[] = "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS origin_actor_type text NULL";
            $statements[] = "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS origin_actor_id text NULL";
            $statements[] = "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}_origin_actor_pair_check";
            $statements[] = "ALTER TABLE {$table} ADD CONSTRAINT {$name}_origin_actor_pair_check CHECK (((origin_actor_type IS NULL) = (origin_actor_id IS NULL)) AND (origin_actor_type IS NULL OR (origin_actor_type <> '' AND origin_actor_id <> '')))";
        }

        return $statements;
    }
}
