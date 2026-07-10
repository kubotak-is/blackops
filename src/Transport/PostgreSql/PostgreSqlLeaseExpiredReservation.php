<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Execution\OperationClaim;

final readonly class PostgreSqlLeaseExpiredReservation
{
    public function __construct(
        public OperationClaim $claim,
        public int $sequence,
        public AttemptContext $attempt,
    ) {}
}
