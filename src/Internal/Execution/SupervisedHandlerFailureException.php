<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use RuntimeException;

final class SupervisedHandlerFailureException extends RuntimeException
{
    public function __construct(
        string $message = '',
        ?\Throwable $previous = null,
        private readonly string $result = 'failed',
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function result(): string
    {
        return in_array($this->result, \BlackOps\Internal\Telemetry\TelemetryMetrics::RESULTS, strict: true)
            ? $this->result
            : 'failed';
    }
}
