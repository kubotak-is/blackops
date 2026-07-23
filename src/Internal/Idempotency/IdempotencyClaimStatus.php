<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

enum IdempotencyClaimStatus: string
{
    case Claimed = 'claimed';
    case ExistingSameFingerprint = 'existing_same_fingerprint';
    case ExistingConflict = 'existing_conflict';
}
