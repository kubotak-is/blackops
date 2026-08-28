<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

interface AuditLogicalRecord
{
    /** @return array<string,mixed> */
    public function toArray(): array;
}
