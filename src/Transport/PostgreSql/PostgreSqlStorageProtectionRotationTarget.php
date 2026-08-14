<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\StorageProtection\StoragePurpose;

final readonly class PostgreSqlStorageProtectionRotationTarget
{
    /** @param list<string> $columns */
    public function __construct(
        public string $table,
        public array $columns,
        public string $identity,
        public string $operationType,
        public string $schema,
    ) {}

    public static function forPurpose(StoragePurpose $purpose): self
    {
        return match ($purpose) {
            StoragePurpose::JournalRecord => new self(
                'journal',
                ['encoded_record'],
                'record_id',
                'operation_type',
                'operation_schema_version',
            ),
            StoragePurpose::DeferredPayload => new self(
                'operations',
                ['encoded_payload'],
                'operation_id',
                'operation_type',
                'schema_version',
            ),
            StoragePurpose::DeferredContext => new self(
                'operations',
                ['encoded_context'],
                'operation_id',
                'operation_type',
                'schema_version',
            ),
            StoragePurpose::OutcomePayload => new self(
                'outcomes',
                ['encoded_payload'],
                'operation_id',
                'operation_type',
                'schema_version',
            ),
            StoragePurpose::OutboxPayload => new self(
                'outbox_records',
                ['encoded_payload'],
                'record_id',
                'operation_type',
                'schema_version',
            ),
            StoragePurpose::OutboxContext => new self(
                'outbox_records',
                ['encoded_context'],
                'record_id',
                'operation_type',
                'schema_version',
            ),
            StoragePurpose::DeadLetterReason => new self('dead_letters', ['encoded_reason'], 'operation_id', '', ''),
            StoragePurpose::IdempotencyResponse => new self(
                'idempotency_records',
                ['encoded_response'],
                'scope_hash',
                'operation_type',
                'application_schema_version',
            ),
            StoragePurpose::IdempotencyResult => new self(
                'idempotency_records',
                ['encoded_result'],
                'scope_hash',
                'operation_type',
                'application_schema_version',
            ),
        };
    }

    public function hasCompositeIdentity(): bool
    {
        return $this->identity === 'scope_hash';
    }
}
