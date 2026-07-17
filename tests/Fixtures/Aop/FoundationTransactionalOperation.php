<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

class FoundationTransactionalOperation implements Operation
{
    #[Transactional]
    public function execute(): string
    {
        return 'operation';
    }
}
