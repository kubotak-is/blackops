<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\Identifier\OperationId;

interface OperationStatusSource
{
    public function findSubject(OperationId $operationId): ?OperationStatusSubject;

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult;
}
