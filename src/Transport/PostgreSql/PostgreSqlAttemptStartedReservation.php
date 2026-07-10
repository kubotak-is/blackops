<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlAttemptStartedReservation
{
    public function __construct(
        public int $sequence,
        public int $attemptNumber,
    ) {}
}
