<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use RuntimeException;

final class OperationDiagnosticsException extends RuntimeException
{
    private function __construct(
        public readonly DiagnosticsFailureCode $diagnosticsCode,
    ) {
        parent::__construct($diagnosticsCode->value);
    }

    public static function storageFailed(): self
    {
        return new self(DiagnosticsFailureCode::StorageFailed);
    }

    public static function decodeFailed(): self
    {
        return new self(DiagnosticsFailureCode::DecodeFailed);
    }

    public static function integrityFailed(): self
    {
        return new self(DiagnosticsFailureCode::IntegrityFailed);
    }
}
