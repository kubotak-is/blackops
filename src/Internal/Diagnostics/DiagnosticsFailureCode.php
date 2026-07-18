<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

enum DiagnosticsFailureCode: string
{
    case StorageFailed = 'diagnostics.storage_failed';
    case DecodeFailed = 'diagnostics.decode_failed';
    case IntegrityFailed = 'diagnostics.integrity_failed';
}
