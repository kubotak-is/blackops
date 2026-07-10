<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\RetentionHoldId;
use DateTimeImmutable;

interface PostgreSqlRetentionHoldIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionHoldId;
}
