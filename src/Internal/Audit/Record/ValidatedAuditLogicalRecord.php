<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditEventDefinition;

final readonly class ValidatedAuditLogicalRecord
{
    /**
     * @param array<string,mixed> $snapshot
     */
    public function __construct(
        public array $snapshot,
        public AuditEventDefinition $definition,
    ) {}
}
