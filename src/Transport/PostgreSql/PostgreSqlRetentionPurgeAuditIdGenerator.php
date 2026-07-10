<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use DateTimeImmutable;

interface PostgreSqlRetentionPurgeAuditIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId;
}
