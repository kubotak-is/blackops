<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

enum AuditRelayOutcome: string
{
    case Idle = 'idle';
    case RecoveredPending = 'recovered_pending';
    case RecoveryPoisoned = 'recovery_poisoned';
    case RelayedInserted = 'relayed_inserted';
    case RelayedExisting = 'relayed_existing';
    case Retried = 'retried';
    case Poisoned = 'poisoned';
    case Stale = 'stale';
}
