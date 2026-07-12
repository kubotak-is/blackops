<?php

declare(strict_types=1);

namespace BlackOps\Internal\Retention;

use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use Psr\Log\LoggerInterface;

final readonly class LoggingRetentionPurgeAuditPort implements RetentionPurgeAuditPort
{
    public function __construct(
        private RetentionPurgeAuditPort $primary,
        private LoggerInterface $logger,
    ) {}

    public function record(RetentionPurgeAuditRecord $record): void
    {
        $this->primary->record($record);
        $this->logger->info('Retention purge audit recorded.', [
            'audit_id' => $record->id()->toString(),
            'operation_id' => $record->operationId()->toString(),
            'target' => $record->target()->value,
            'affected_count' => $record->affectedCount(),
            'policy' => $record->policy()->toString(),
            'purged_at' => $record->purgedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'purged_by' => $record->purgedBy()->toString(),
        ]);
    }
}
