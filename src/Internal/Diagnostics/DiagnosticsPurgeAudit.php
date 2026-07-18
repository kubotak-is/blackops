<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Retention\RetentionPurgeTarget;

final readonly class DiagnosticsPurgeAudit
{
    public function __construct(
        public RetentionPurgeTarget $target,
        public int $affectedCount,
        public string $purgedAt,
    ) {}
}
