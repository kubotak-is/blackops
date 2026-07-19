<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

enum PostgreSqlStatusFailureKind
{
    case Storage;
    case Integrity;
}
