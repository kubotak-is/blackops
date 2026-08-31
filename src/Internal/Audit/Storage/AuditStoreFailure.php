<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

enum AuditStoreFailure: string
{
    case ResourceUnavailable = 'resource_unavailable';
    case IdempotencyConflict = 'idempotency_conflict';
    case SegmentConflict = 'segment_conflict';
    case StorageUnavailable = 'storage_unavailable';
    case IntegrityInvalid = 'integrity_invalid';
}
