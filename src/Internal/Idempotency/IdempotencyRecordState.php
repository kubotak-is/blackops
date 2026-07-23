<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

enum IdempotencyRecordState: string
{
    case Processing = 'processing';
    case Terminal = 'terminal';
}
