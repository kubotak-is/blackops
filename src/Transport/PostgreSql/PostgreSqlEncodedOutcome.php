<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlEncodedOutcome
{
    public function __construct(
        public string $type,
        public int $schemaVersion,
        public string $payload,
    ) {}
}
