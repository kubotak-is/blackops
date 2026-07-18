<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

enum PostgreSqlDiagnosticsFailureKind
{
    case Storage;
    case Integrity;
}
