<?php

declare(strict_types=1);

namespace BlackOps\Outcome;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;

#[PublicApi]
interface OutcomeReader
{
    public function find(OperationId $operationId): ?OutcomeRecord;
}
