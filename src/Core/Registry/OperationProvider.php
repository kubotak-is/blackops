<?php

declare(strict_types=1);

namespace BlackOps\Core\Registry;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;

#[PublicApi]
interface OperationProvider
{
    /**
     * @return iterable<class-string<Operation>>
     */
    public function definitions(): iterable;
}
