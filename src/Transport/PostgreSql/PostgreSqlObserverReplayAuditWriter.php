<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlObserverReplayAuditWriter
{
    public function __construct(
        private Connection $connection,
        private string $table,
    ) {}

    public function insert(
        PostgreSqlObserverReplayBeginRequest $request,
        string $selectorHash,
        string $targetHash,
        string $auditId,
        DateTimeImmutable $now,
    ): void {
        $this->connection->executeStatement(
            "INSERT INTO {$this->table} (audit_id, checkpoint_id, selector_kind, selector_hash, target_hash, selector_operation_id, selector_record_id, selector_from, selector_to, target_names, actor, reason, state, started_at) VALUES (:audit,:id,:kind,:selector,:target,:operation,:record,:from_at,:to_at,:targets,:actor,:reason,'started',:at)",
            [
                'audit' => $auditId,
                'id' => $request->checkpoint,
                'kind' => $request->selector->kind,
                'selector' => $selectorHash,
                'target' => $targetHash,
                'operation' => $request->selector->operationId?->toString(),
                'record' => $request->selector->recordId?->toString(),
                'from_at' => $request->selector->from?->format('Y-m-d H:i:s.uP'),
                'to_at' => $request->selector->to?->format('Y-m-d H:i:s.uP'),
                'targets' => json_encode($request->targets, JSON_THROW_ON_ERROR),
                'actor' => $request->actor,
                'reason' => $request->reason,
                'at' => $now->format('Y-m-d H:i:s.uP'),
            ],
        );
    }
}
