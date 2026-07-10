<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlFailureReservation
{
    public function __construct(
        public int $sequence,
    ) {}
}
