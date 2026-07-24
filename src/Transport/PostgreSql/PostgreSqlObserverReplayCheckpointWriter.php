<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlObserverReplayCheckpointWriter
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed>|false */
    public function row(string $table, string $checkpoint): array|false
    {
        return $this->connection->fetchAssociative(
            "SELECT selector_hash, target_hash, cursor_record_id, state FROM {$table} WHERE checkpoint_id = :id FOR UPDATE",
            ['id' => $checkpoint],
        );
    }

    public function insert(
        string $table,
        PostgreSqlObserverReplayBeginRequest $request,
        string $selectorHash,
        string $targetHash,
        DateTimeImmutable $now,
    ): void {
        $this->connection->executeStatement(
            "INSERT INTO {$table} (checkpoint_id, selector_hash, target_hash, selector_kind, selector_operation_id, selector_record_id, selector_from, selector_to, target_names, state, cursor_record_id, selected_count, delivered_count, updated_at) VALUES (:id,:selector,:target,:kind,:operation,:record,:from_at,:to_at,:targets,'running',NULL,0,0,:at)",
            [
                'id' => $request->checkpoint,
                'selector' => $selectorHash,
                'target' => $targetHash,
                'kind' => $request->selector->kind,
                'operation' => $request->selector->operationId?->toString(),
                'record' => $request->selector->recordId?->toString(),
                'from_at' => $request->selector->from?->format('Y-m-d H:i:s.uP'),
                'to_at' => $request->selector->to?->format('Y-m-d H:i:s.uP'),
                'targets' => json_encode($request->targets, JSON_THROW_ON_ERROR),
                'at' => $now->format('Y-m-d H:i:s.uP'),
            ],
        );
    }

    public function resume(string $table, string $checkpoint, DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            "UPDATE {$table} SET state='running', updated_at=:at WHERE checkpoint_id=:id",
            ['id' => $checkpoint, 'at' => $now->format('Y-m-d H:i:s.uP')],
        );
    }
}
