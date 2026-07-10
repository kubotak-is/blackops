<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Supervision\RetryableException;
use RuntimeException;

final class LeaseExpiredException extends RuntimeException implements RetryableException
{
    public function __construct()
    {
        parent::__construct('Deferred operation lease expired.');
    }
}
