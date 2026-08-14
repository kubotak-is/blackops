<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use DateTimeImmutable;

final readonly class ScheduleOccurrence
{
    public function __construct(
        public string $scheduleName,
        public DateTimeImmutable $scheduledAt,
        public DateTimeImmutable $evaluatedAt,
        public string $state,
        public ?string $category,
        public ?OperationId $operationId,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?TenantRef $tenant = null,
    ) {}
}
