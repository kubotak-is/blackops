<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use RuntimeException;

/**
 * Owns the Audit-specific BOPD key, AAD, and post-decrypt binding boundary.
 * All failures intentionally collapse to the one value-free exception.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AuditProtectedBodyCodec
{
    private const int BOPD_FIXED_OVERHEAD_BYTES = 52;

    private const int BOPD_MAX_KEY_ID_BYTES = 128;

    private const int RECORD_ENVELOPE_MAX_BYTES = AuditRecordCodec::MAX_BYTES + 180;

    private const int OUTBOX_MAX_BYTES = AuditRecordCodec::MAX_BYTES;

    private const int OUTBOX_PLAINTEXT_MAX_BYTES =
        self::OUTBOX_MAX_BYTES - self::BOPD_FIXED_OVERHEAD_BYTES - self::BOPD_MAX_KEY_ID_BYTES;

    public function __construct(
        private BopdBinaryEnvelopeCodec $binary,
        private AuditProtectionKeySource $keys,
        private NonceSource $nonces,
        private AuditAssociatedData $aad,
        private AuditRecordCodec $records,
        private AuditIngressEnvelopeCodec $ingress,
    ) {}

    public function protectRecord(#[\SensitiveParameter] CompleteAuditRecord $record): AuditStoredRecord
    {
        try {
            $logical = $record->logicalSnapshot();
            $tenant = $this->tenant($logical['tenant'] ?? null);
            $plaintext = $record->bytes();
            if (strlen($plaintext) > AuditRecordCodec::MAX_BYTES) {
                throw new RuntimeException('Audit record body is too large.');
            }
            $key = $this->keys->activeKey($tenant, AuditProtectionPurpose::AuditRecord);
            $this->assertKey($key);
            $body = $this->binary->seal(
                $plaintext,
                $this->aad->recordForWrite($key->id(), $record),
                $key,
                $this->nonces->generate(),
                AuditRecordCodec::MAX_BYTES,
                self::RECORD_ENVELOPE_MAX_BYTES,
            );
            $integrity = $record->integrity()->toArray();
            return AuditStoredRecord::fromArray([
                'schemaVersion' => 1,
                'kind' => 'audit_stored_record',
                'recordId' => $logical['recordId'],
                'partitionId' => $integrity['partitionId'],
                'tenantBinding' => $tenant?->toArray(),
                'segmentId' => $integrity['segmentId'],
                'sequence' => $integrity['sequence'],
                'previousRecordHash' => $integrity['previousRecordHash'],
                'contentHash' => $integrity['contentHash'],
                'recordHash' => $integrity['recordHash'],
                'recordedAt' => $this->logicalRecordedAt($logical),
                'eventCategory' => $this->logicalEventCategory($logical),
                'protectedBody' => $body,
            ]);
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    public function unprotectRecord(
        #[\SensitiveParameter]
        AuditStoredRecord $record,
        ?AuditTypedReference $expectedTenant,
        string $expectedPartitionId,
    ): CompleteAuditRecord {
        try {
            $row = $record->toArray();
            $rowTenant = $this->tenant($row['tenantBinding'] ?? null);
            if (!$this->sameTenant($rowTenant, $expectedTenant) || $row['partitionId'] !== $expectedPartitionId) {
                throw new RuntimeException('Audit row binding mismatch.');
            }
            $parsed = $this->binary->parse($record->protectedBody(), self::RECORD_ENVELOPE_MAX_BYTES);
            $key = $this->keys->key($parsed->keyId(), $expectedTenant, AuditProtectionPurpose::AuditRecord);
            $this->assertKey($key);
            if (!hash_equals($parsed->keyId(), $key->id())) {
                throw new RuntimeException('Audit key mismatch.');
            }
            $plaintext = $this->binary->open(
                $parsed,
                $this->aad->recordForRead($parsed->keyId(), $record),
                $key,
                AuditRecordCodec::MAX_BYTES,
            );
            $complete = $this->records->decode($plaintext);
            $logical = $complete->logicalSnapshot();
            $integrity = $complete->integrity()->toArray();
            if (
                $logical['recordId'] !== $row['recordId']
                || $logical['tenant'] !== $row['tenantBinding']
                || $this->logicalRecordedAt($logical) !== $row['recordedAt']
                || $this->logicalEventCategory($logical) !== $row['eventCategory']
                || $integrity['partitionId'] !== $row['partitionId']
                || $integrity['segmentId'] !== $row['segmentId']
                || $integrity['sequence'] !== $row['sequence']
                || $integrity['previousRecordHash'] !== $row['previousRecordHash']
                || $integrity['contentHash'] !== $row['contentHash']
                || $integrity['recordHash'] !== $row['recordHash']
            ) {
                throw new RuntimeException('Audit record binding mismatch.');
            }
            return $complete;
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    public function protectOutbox(#[\SensitiveParameter] AuditIngressEnvelope $envelope): AuditProtectedIngress
    {
        try {
            $value = $envelope->toArray();
            $tenant = $this->tenant($value['tenantBinding'] ?? null);
            $plaintext = $this->ingress->encode($envelope);
            if (strlen($plaintext) > self::OUTBOX_PLAINTEXT_MAX_BYTES) {
                throw new RuntimeException('Audit outbox body is too large.');
            }
            $key = $this->keys->activeKey($tenant, AuditProtectionPurpose::AuditOutboxRecord);
            $this->assertKey($key);
            $body = $this->binary->seal(
                $plaintext,
                $this->aad->outboxForWrite($key->id(), $envelope),
                $key,
                $this->nonces->generate(),
                AuditRecordCodec::MAX_BYTES,
                self::OUTBOX_MAX_BYTES,
            );
            return AuditProtectedIngress::fromArray([
                'recordId' => $value['recordId'],
                'partitionId' => $value['partitionId'],
                'tenantBinding' => $tenant?->toArray(),
                'ingressOrdinal' => $value['ingressOrdinal'],
                'idempotencyKeyHash' => $this->ingress->idempotencyKeyHash($envelope),
                'contentHash' => $value['contentHash'],
                'protectedBody' => $body,
            ]);
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    public function unprotectOutbox(#[\SensitiveParameter] AuditProtectedIngress $ingress): AuditIngressEnvelope
    {
        try {
            $row = $ingress->toArray();
            $tenant = $this->tenant($row['tenantBinding'] ?? null);
            $parsed = $this->binary->parse($ingress->protectedBody(), self::OUTBOX_MAX_BYTES);
            $key = $this->keys->key($parsed->keyId(), $tenant, AuditProtectionPurpose::AuditOutboxRecord);
            $this->assertKey($key);
            if (!hash_equals($parsed->keyId(), $key->id())) {
                throw new RuntimeException('Audit key mismatch.');
            }
            $plaintext = $this->binary->open(
                $parsed,
                $this->aad->outboxForRead($parsed->keyId(), $ingress),
                $key,
                AuditRecordCodec::MAX_BYTES,
            );
            $envelope = $this->ingress->decode($plaintext);
            $value = $envelope->toArray();
            if (
                $value['recordId'] !== $row['recordId']
                || $value['partitionId'] !== $row['partitionId']
                || $value['tenantBinding'] !== $row['tenantBinding']
                || $value['ingressOrdinal'] !== $row['ingressOrdinal']
                || $value['contentHash'] !== $row['contentHash']
                || $this->ingress->idempotencyKeyHash($envelope) !== $row['idempotencyKeyHash']
            ) {
                throw new RuntimeException('Audit ingress binding mismatch.');
            }
            return $envelope;
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    private function assertKey(mixed $key): void
    {
        if (!$key instanceof StorageKey) {
            throw new RuntimeException('Audit key is unavailable.');
        }
    }

    private function tenant(mixed $value): ?AuditTypedReference
    {
        if ($value === null) {
            return null;
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['type', 'id']
            || !is_string($value['type'])
            || !is_string($value['id'])
        ) {
            throw new RuntimeException('Audit tenant is unavailable.');
        }
        return new AuditTypedReference($value['type'], $value['id']);
    }

    private function sameTenant(?AuditTypedReference $left, ?AuditTypedReference $right): bool
    {
        return $left?->toArray() === $right?->toArray();
    }

    /** @param array<string,mixed> $logical */
    private function logicalRecordedAt(array $logical): string
    {
        $time = $logical['time'] ?? null;
        if (!is_array($time)) {
            throw new RuntimeException('Audit record time is unavailable.');
        }
        $recordedAt = $time['recordedAt'] ?? null;
        if (!is_string($recordedAt)) {
            throw new RuntimeException('Audit record time is unavailable.');
        }
        return $recordedAt;
    }

    /** @param array<string,mixed> $logical */
    private function logicalEventCategory(array $logical): string
    {
        $event = $logical['event'] ?? null;
        if (!is_array($event)) {
            throw new RuntimeException('Audit record event is unavailable.');
        }
        $category = $event['category'] ?? null;
        if (!is_string($category)) {
            throw new RuntimeException('Audit record event is unavailable.');
        }
        return $category;
    }
}
