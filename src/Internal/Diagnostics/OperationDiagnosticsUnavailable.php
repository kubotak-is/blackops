<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class OperationDiagnosticsUnavailable implements OperationDiagnosticsResult
{
    public string $code;

    public function __construct()
    {
        $this->code = 'operation.unavailable';
    }
}
