<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Read;

interface OperationReadNonceSource
{
    public function generate(): string;
}
