<?php

declare(strict_types=1);

namespace BlackOps\Http\Status;

final readonly class OperationStatusHttpContract
{
    public const int SCHEMA_VERSION = 1;
    public const int POLLING_HINT_SECONDS = 1;
    public const string CACHE_CONTROL = 'private, no-store';
}
