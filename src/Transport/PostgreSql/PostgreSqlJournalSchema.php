<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlJournalSchema
{
    private PostgreSqlIdentifier $identifier;
    private string $schemaName;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
        $this->schemaName = $schema;
    }

    /**
     * @return list<string>
     */
    public function statements(): array
    {
        $schema = $this->identifier->quoted();
        $migrations = $this->identifier->qualify('schema_migrations');
        $journal = $this->identifier->qualify('journal');

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$migrations} (
                version varchar(191) PRIMARY KEY,
                executed_at timestamp(0) without time zone NULL,
                execution_time integer NULL
            )",
            "DO \$blackops_metadata\$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = '{$this->schemaName}'
                      AND table_name = 'schema_migrations'
                      AND column_name = 'applied_at'
                ) THEN
                    ALTER TABLE {$migrations}
                        ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL;
                    UPDATE {$migrations}
                        SET executed_at = applied_at AT TIME ZONE 'UTC'
                        WHERE executed_at IS NULL;
                    ALTER TABLE {$migrations} DROP COLUMN applied_at;
                END IF;
            END
            \$blackops_metadata\$",
            "ALTER TABLE {$migrations}
                ADD COLUMN IF NOT EXISTS executed_at timestamp(0) without time zone NULL",
            "ALTER TABLE {$migrations}
                ADD COLUMN IF NOT EXISTS execution_time integer NULL",
            "ALTER TABLE {$migrations}
                ALTER COLUMN version TYPE varchar(191)",
            "CREATE TABLE IF NOT EXISTS {$journal} (
                record_id uuid PRIMARY KEY,
                operation_id uuid NOT NULL,
                sequence bigint NOT NULL,
                event text NOT NULL,
                attempt_id uuid NULL,
                schema_version integer NOT NULL CHECK (schema_version >= 1),
                occurred_at timestamptz NOT NULL,
                encoded_record bytea NOT NULL,
                UNIQUE (operation_id, sequence)
            )",
            "CREATE INDEX IF NOT EXISTS journal_operation_sequence_idx
                ON {$journal} (operation_id, sequence)",
            "CREATE INDEX IF NOT EXISTS journal_event_occurred_at_idx
                ON {$journal} (event, occurred_at)",
        ];
    }

    public function journalTable(): string
    {
        return $this->identifier->qualify('journal');
    }
}
