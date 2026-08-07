<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\TenantRef;

final readonly class PostgreSqlStatusSubject
{
    public function __construct(
        public string $operationId,
        public string $operationType,
        public ?string $originActorId,
        public ?string $originActorType,
        public ?TenantRef $tenant = null,
    ) {}
}
