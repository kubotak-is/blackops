<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlScheduleSchema;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlScheduleStore
{
    private PostgreSqlScheduleSchema $schema;
    private string $schemaName;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schemaName = $schema;
        $this->schema = new PostgreSqlScheduleSchema($schema);
    }

    public function migrate(): void
    {
        foreach ($this->schema->statements() as $statement)
            $this->connection->executeStatement($statement);
    }

    /** @param callable(): mixed $callback */
    public function withScheduleLock(string $scheduleName, callable $callback): mixed
    {
        $lockKey = 'blackops.scheduled-operation:' . $this->schemaName . ':' . $scheduleName;
        $this->connection->executeQuery('SELECT pg_advisory_lock(hashtextextended(:lock_key, 0))', [
            'lock_key' => $lockKey,
        ]);

        try {
            return $callback();
        } finally {
            $this->connection->executeQuery('SELECT pg_advisory_unlock(hashtextextended(:lock_key, 0))', [
                'lock_key' => $lockKey,
            ]);
        }
    }

    /** @return list<ScheduleOccurrence> */
    public function recoverClaimed(string $scheduleName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT schedule_name, scheduled_at, evaluated_at, state, category, operation_id::text AS operation_id, tenant_type, tenant_id, accepted_at FROM {$this->schema->occurrencesTable()} WHERE schedule_name = :name AND state = 'claimed' ORDER BY scheduled_at, created_at",
            ['name' => $scheduleName],
        );
        return array_map($this->fromRow(...), $rows);
    }

    public function occurrence(string $scheduleName, DateTimeImmutable $scheduledAt): ?ScheduleOccurrence
    {
        $row = $this->connection->fetchAssociative(
            "SELECT schedule_name, scheduled_at, evaluated_at, state, category, operation_id::text AS operation_id, tenant_type, tenant_id, accepted_at FROM {$this->schema->occurrencesTable()} WHERE schedule_name = :name AND scheduled_at = :scheduled_at",
            ['name' => $scheduleName, 'scheduled_at' => $this->timestamp($scheduledAt)],
        );
        return $row === false ? null : $this->fromRow($row);
    }

    /** @param array<string,mixed> $row */
    private function fromRow(array $row): ScheduleOccurrence
    {
        return new ScheduleOccurrence(
            (string) $row['schedule_name'],
            new DateTimeImmutable((string) $row['scheduled_at']),
            new DateTimeImmutable((string) $row['evaluated_at']),
            (string) $row['state'],
            $row['category'] === null ? null : (string) $row['category'],
            $row['operation_id'] === null ? null : OperationId::fromString((string) $row['operation_id']),
            $row['accepted_at'] === null ? null : new DateTimeImmutable((string) $row['accepted_at']),
            ($row['tenant_type'] ?? null) === null && ($row['tenant_id'] ?? null) === null
                ? null
                : new \BlackOps\Core\TenantRef((string) $row['tenant_type'], (string) $row['tenant_id']),
        );
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
