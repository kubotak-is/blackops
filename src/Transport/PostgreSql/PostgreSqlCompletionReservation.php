<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlCompletionReservation
{
    public function __construct(
        public int $attemptSucceededSequence,
        public int $operationCompletedSequence,
    ) {}
}
