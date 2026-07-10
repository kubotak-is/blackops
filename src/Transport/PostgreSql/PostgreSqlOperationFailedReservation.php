<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlOperationFailedReservation
{
    public function __construct(
        public int $sequence,
    ) {}
}
