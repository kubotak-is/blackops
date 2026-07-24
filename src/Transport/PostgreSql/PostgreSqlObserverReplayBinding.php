<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlObserverReplayBinding
{
    public function __construct(
        public ?string $cursor,
        public string $auditId,
    ) {}
}
