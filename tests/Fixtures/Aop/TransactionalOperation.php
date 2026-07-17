<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

#[OperationType('application.build.transactional')]
#[Transactional]
readonly class TransactionalOperation implements Operation
{
    public function handle(TransactionalOperationValue $value): TransactionalOperationOutcome
    {
        return new TransactionalOperationOutcome();
    }
}
