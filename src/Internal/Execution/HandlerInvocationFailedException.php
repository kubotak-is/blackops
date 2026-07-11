<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use RuntimeException;
use Throwable;

final class HandlerInvocationFailedException extends RuntimeException
{
    public function __construct(
        public readonly Throwable $failure,
    ) {
        parent::__construct('Deferred handler invocation failed.', previous: $failure);
    }
}
