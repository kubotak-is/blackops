<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Retention\RetentionPurgeTarget;

final readonly class PostgreSqlDiagnosticsPurgeAudit
{
    public function __construct(
        public RetentionPurgeTarget $target,
        public int $affectedCount,
        public string $purgedAt,
    ) {}
}
