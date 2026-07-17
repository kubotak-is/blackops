<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Core\OperationResult;
use RuntimeException;

final class OperationTransactionRejected extends RuntimeException
{
    public function __construct(
        public readonly OperationResult $result,
    ) {
        parent::__construct('Transactional operation was rejected.');
    }
}
