<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use DateTimeImmutable;

final readonly class OperationStatusJournalAttempt
{
    public function __construct(
        public string $id,
        public int $number,
        public DateTimeImmutable $startedAt,
    ) {}
}
