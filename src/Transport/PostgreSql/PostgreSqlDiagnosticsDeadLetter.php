<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlDiagnosticsDeadLetter
{
    public function __construct(
        public string $operationId,
        public ?string $finalAttemptId,
        public ?int $finalAttemptNumber,
        public string $reasonType,
        public string $movedAt,
    ) {}
}
