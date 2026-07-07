<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlJournalSchema
{
    private PostgreSqlIdentifier $identifier;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
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
                version text PRIMARY KEY,
                applied_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
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
