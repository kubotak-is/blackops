<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlOutcomeSchema
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
        $operations = $this->identifier->qualify('operations');
        $outcomes = $this->table();

        return [
            "CREATE SCHEMA IF NOT EXISTS {$schema}",
            "CREATE TABLE IF NOT EXISTS {$outcomes} (
                operation_id uuid PRIMARY KEY,
                outcome_type text NOT NULL CHECK (outcome_type <> ''),
                schema_version integer NOT NULL CHECK (schema_version >= 1),
                encoded_payload bytea NOT NULL,
                completed_at timestamptz NOT NULL
            )",
            "ALTER TABLE {$outcomes}
                DROP CONSTRAINT IF EXISTS outcomes_operation_id_fkey",
            "ALTER TABLE {$outcomes}
                ADD CONSTRAINT outcomes_operation_id_fkey
                FOREIGN KEY (operation_id)
                REFERENCES {$operations} (operation_id)
                ON DELETE RESTRICT",
            "CREATE INDEX IF NOT EXISTS outcomes_completed_at_idx
                ON {$outcomes} (completed_at, operation_id)",
        ];
    }

    public function table(): string
    {
        return $this->identifier->qualify('outcomes');
    }

    public function operationsTable(): string
    {
        return $this->identifier->qualify('operations');
    }
}
