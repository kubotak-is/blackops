<?php

declare(strict_types=1);

namespace BlackOps\Outcome;

use BlackOps\Core\Identifier\OperationId;

interface OutcomeReader
{
    public function find(OperationId $operationId): ?OutcomeRecord;
}
