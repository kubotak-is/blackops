<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlRetentionPurgeAuditStore implements RetentionPurgeAuditPort
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function record(RetentionPurgeAuditRecord $record): void
    {
        try {
            $table = $this->schema->retentionPurgeAuditsTable();
            $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    audit_id,
                    operation_id,
                    target,
                    affected_count,
                    policy,
                    purged_at,
                    purged_by
                ) VALUES (
                    :audit_id,
                    :operation_id,
                    :target,
                    :affected_count,
                    :policy,
                    :purged_at,
                    :purged_by
                )",
                [
                    'audit_id' => $record->id()->toString(),
                    'operation_id' => $record->operationId()->toString(),
                    'target' => $record->target()->value,
                    'affected_count' => $record->affectedCount(),
                    'policy' => $record->policy()->toString(),
                    'purged_at' => $this->formatTimestamp($record->purgedAt()),
                    'purged_by' => $record->purgedBy()->toString(),
                ],
            );
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to record PostgreSQL retention purge audit.',
                previous: $exception,
            );
        }
    }

    private function formatTimestamp(\DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
