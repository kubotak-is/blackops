<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlScheduleSchema
{
    private PostgreSqlIdentifier $identifier;

    public function __construct(string $schema = 'blackops')
    {
        $this->identifier = PostgreSqlIdentifier::schema($schema);
    }

    /** @return list<string> */
    public function statements(): array
    {
        $states = $this->table('schedule_states');
        $occurrences = $this->table('schedule_occurrences');
        return [
            'CREATE SCHEMA IF NOT EXISTS ' . $this->identifier->quoted(),
            "CREATE TABLE IF NOT EXISTS {$states} (
                schedule_name text PRIMARY KEY CHECK (schedule_name ~ '^[a-z0-9]+(?:\\.[a-z0-9]+)*$'),
                operation_type text NOT NULL CHECK (operation_type <> ''),
                cursor_at timestamptz NOT NULL CHECK (date_trunc('minute', cursor_at) = cursor_at),
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$occurrences} (
                schedule_name text NOT NULL,
                scheduled_at timestamptz NOT NULL CHECK (date_trunc('minute', scheduled_at) = scheduled_at),
                evaluated_at timestamptz NOT NULL,
                state text NOT NULL CHECK (state IN ('claimed','accepted','completed','rejected','failed','dead_lettered','skipped_misfire','skipped_overlap')),
                category text NULL CHECK (category IS NULL OR category <> ''),
                operation_id uuid NULL UNIQUE,
                accepted_at timestamptz NULL,
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL,
                PRIMARY KEY (schedule_name, scheduled_at),
                CONSTRAINT schedule_occurrences_schedule_fkey FOREIGN KEY (schedule_name) REFERENCES {$states} (schedule_name) ON DELETE RESTRICT,
                CHECK ((state IN ('claimed','accepted','completed','rejected','failed','dead_lettered') AND operation_id IS NOT NULL)
                    OR (state IN ('skipped_misfire','skipped_overlap') AND operation_id IS NULL))
            )",
            "CREATE INDEX IF NOT EXISTS schedule_occurrences_recovery_idx ON {$occurrences} (schedule_name, scheduled_at, created_at) WHERE state = 'claimed'",
            "CREATE INDEX IF NOT EXISTS schedule_occurrences_state_idx ON {$occurrences} (schedule_name, state, scheduled_at)",
        ];
    }

    public function statesTable(): string
    {
        return $this->table('schedule_states');
    }

    public function occurrencesTable(): string
    {
        return $this->table('schedule_occurrences');
    }

    private function table(string $name): string
    {
        return $this->identifier->qualify($name);
    }
}
