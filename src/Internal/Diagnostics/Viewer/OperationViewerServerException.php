<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

use RuntimeException;

final class OperationViewerServerException extends RuntimeException
{
    private function __construct(
        public readonly string $safeCode,
    ) {
        parent::__construct($safeCode);
    }

    public static function bindFailed(): self
    {
        return new self('viewer.bind_failed');
    }

    public static function unavailable(): self
    {
        return new self('viewer.runtime_unavailable');
    }
}
