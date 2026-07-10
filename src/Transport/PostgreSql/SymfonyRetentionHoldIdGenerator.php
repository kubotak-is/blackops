<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\RetentionHoldId;
use DateTimeImmutable;
use Symfony\Component\Uid\UuidV7;

final readonly class SymfonyRetentionHoldIdGenerator implements PostgreSqlRetentionHoldIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionHoldId
    {
        return RetentionHoldId::fromString(UuidV7::generate($time));
    }
}
