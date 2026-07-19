<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlStatusSubject
{
    public function __construct(
        public string $operationId,
        public string $operationType,
        public ?string $originActorId,
        public ?string $originActorType,
    ) {}
}
