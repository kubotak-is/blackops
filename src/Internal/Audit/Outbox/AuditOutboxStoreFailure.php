<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

enum AuditOutboxStoreFailure: string
{
    case IntegrityInvalid = 'integrity_invalid';
    case IdempotencyConflict = 'idempotency_conflict';
    case TransitionConflict = 'transition_conflict';
    case ResourceUnavailable = 'resource_unavailable';
    case StorageUnavailable = 'storage_unavailable';
}
