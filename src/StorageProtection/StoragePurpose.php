<?php

declare(strict_types=1);

namespace BlackOps\StorageProtection;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum StoragePurpose: string
{
    case JournalRecord = 'journal_record';
    case DeferredPayload = 'deferred_payload';
    case DeferredContext = 'deferred_context';
    case OutcomePayload = 'outcome_payload';
    case OutboxPayload = 'outbox_payload';
    case OutboxContext = 'outbox_context';
    case DeadLetterReason = 'dead_letter_reason';
    case IdempotencyResponse = 'idempotency_response';
    case IdempotencyResult = 'idempotency_result';
}
