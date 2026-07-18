<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class OperationDiagnosticsFound implements OperationDiagnosticsResult
{
    public function __construct(
        public OperationDiagnostics $diagnostics,
    ) {}
}
