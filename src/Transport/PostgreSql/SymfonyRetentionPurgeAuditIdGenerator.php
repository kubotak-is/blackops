<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use DateTimeImmutable;
use Symfony\Component\Uid\UuidV7;

final readonly class SymfonyRetentionPurgeAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString(UuidV7::generate($time));
    }
}
