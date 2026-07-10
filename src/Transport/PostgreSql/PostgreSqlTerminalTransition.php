<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use SensitiveParameter;

final readonly class PostgreSqlTerminalTransition
{
    public function __construct(
        public OperationId $operationId,
        #[SensitiveParameter]
        public int $fencingToken,
        public string $fromState,
        public string $toState,
        public int $nextSequence,
        public DateTimeImmutable $updatedAt,
    ) {}
}
