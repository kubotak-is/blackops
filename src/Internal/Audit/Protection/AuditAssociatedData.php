<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class AuditAssociatedData
{
    private const string RECORD_DOMAIN = 'blackops.audit.record.aad.v1';

    private const string OUTBOX_DOMAIN = 'blackops.audit.outbox.aad.v1';

    public function recordForWrite(#[\SensitiveParameter] string $keyId, CompleteAuditRecord $record): string
    {
        $logical = $record->logicalSnapshot();
        $integrity = $record->integrity()->toArray();
        return $this->record(
            self::RECORD_DOMAIN,
            $keyId,
            $integrity['recordId'] ?? $logical['recordId'] ?? null,
            $integrity['partitionId'] ?? null,
            $logical['tenant'] ?? null,
            $integrity['segmentId'] ?? null,
            $integrity['sequence'] ?? null,
            $integrity['previousRecordHash'] ?? null,
            $integrity['contentHash'] ?? null,
            $integrity['recordHash'] ?? null,
            $logical['time']['recordedAt'] ?? null,
            $logical['event']['category'] ?? null,
        );
    }

    public function recordForRead(#[\SensitiveParameter] string $keyId, AuditStoredRecord $record): string
    {
        $value = $record->toArray();
        return $this->record(
            self::RECORD_DOMAIN,
            $keyId,
            $value['recordId'] ?? null,
            $value['partitionId'] ?? null,
            $value['tenantBinding'] ?? null,
            $value['segmentId'] ?? null,
            $value['sequence'] ?? null,
            $value['previousRecordHash'] ?? null,
            $value['contentHash'] ?? null,
            $value['recordHash'] ?? null,
            $value['recordedAt'] ?? null,
            $value['eventCategory'] ?? null,
        );
    }

    public function outboxForWrite(#[\SensitiveParameter] string $keyId, AuditIngressEnvelope $envelope): string
    {
        $value = $envelope->toArray();
        $tenant = $value['tenantBinding'] ?? null;
        $partition = $value['partitionId'] ?? null;
        $recordId = $value['recordId'] ?? null;
        $ordinal = $value['ingressOrdinal'] ?? null;
        $contentHash = $value['contentHash'] ?? null;
        if (!is_string($partition) || !is_string($recordId) || !is_int($ordinal) || !is_string($contentHash)) {
            throw new RuntimeException('Malformed audit ingress.');
        }
        $idempotencyHash = $this->idempotencyHash($partition, $envelope->idempotencyKey());
        return implode('', [
            $this->lp(self::OUTBOX_DOMAIN),
            $this->lp('AuditOutboxRecord'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp($keyId),
            $this->lp($recordId),
            $this->lp($partition),
            $this->lp($this->tenantUnion($tenant)),
            $this->lp((string) $ordinal),
            $this->lp($this->hashBytes($idempotencyHash)),
            $this->lp($this->hashBytes($contentHash)),
        ]);
    }

    public function outboxForRead(#[\SensitiveParameter] string $keyId, AuditProtectedIngress $ingress): string
    {
        $value = $ingress->toArray();
        if (
            !is_string($value['recordId'] ?? null)
            || !is_string($value['partitionId'] ?? null)
            || !is_int($value['ingressOrdinal'] ?? null)
            || !is_string($value['idempotencyKeyHash'] ?? null)
            || !is_string($value['contentHash'] ?? null)
        ) {
            throw new RuntimeException('Malformed audit ingress.');
        }
        return implode('', [
            $this->lp(self::OUTBOX_DOMAIN),
            $this->lp('AuditOutboxRecord'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp($keyId),
            $this->lp($value['recordId']),
            $this->lp($value['partitionId']),
            $this->lp($this->tenantUnion($value['tenantBinding'] ?? null)),
            $this->lp((string) $value['ingressOrdinal']),
            $this->lp($this->hashBytes($value['idempotencyKeyHash'])),
            $this->lp($this->hashBytes($value['contentHash'])),
        ]);
    }

    private function record(
        string $domain,
        string $keyId,
        mixed $recordId,
        mixed $partition,
        mixed $tenant,
        mixed $segment,
        mixed $sequence,
        mixed $previous,
        mixed $content,
        mixed $record,
        mixed $recordedAt,
        mixed $category,
    ): string {
        if (
            !is_string($recordId)
            || !is_string($partition)
            || !is_string($segment)
            || !is_int($sequence)
            || $previous !== null && !is_string($previous)
            || !is_string($content)
            || !is_string($record)
            || !is_string($recordedAt)
            || !is_string($category)
        ) {
            throw new RuntimeException('Malformed audit record.');
        }
        return implode('', [
            $this->lp($domain),
            $this->lp('AuditStoredRecord'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp('1'),
            $this->lp($keyId),
            $this->lp($recordId),
            $this->lp($partition),
            $this->lp($this->tenantUnion($tenant)),
            $this->lp($segment),
            $this->lp((string) $sequence),
            $this->lp($this->previousUnion($previous)),
            $this->lp($this->hashBytes($content)),
            $this->lp($this->hashBytes($record)),
            $this->lp($recordedAt),
            $this->lp($category),
        ]);
    }

    private function tenantUnion(mixed $tenant): string
    {
        if ($tenant === null) {
            return "\0";
        }
        if ($tenant instanceof AuditTypedReference) {
            return "\1" . $this->lp($tenant->type) . $this->lp($tenant->id);
        }
        if (
            !is_array($tenant)
            || array_keys($tenant) !== ['type', 'id']
            || !is_string($tenant['type'])
            || !is_string($tenant['id'])
        ) {
            throw new RuntimeException('Malformed audit tenant.');
        }
        return "\1" . $this->lp($tenant['type']) . $this->lp($tenant['id']);
    }

    private function previousUnion(?string $hash): string
    {
        return $hash === null ? "\0" : "\1" . $this->hashBytes($hash);
    }

    private function idempotencyHash(string $partition, string $key): string
    {
        return hash(
            'sha256',
            $this->stringEncoding('blackops.audit.outbox.idempotency.v1') . $this->stringEncoding($partition)
                . $this->stringEncoding($key),
        );
    }

    private function stringEncoding(string $value): string
    {
        return 's:' . strlen($value) . ':' . $value;
    }

    private function hashBytes(string $hash): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('Malformed audit hash.');
        }
        $value = hex2bin($hash);
        if ($value === false) {
            throw new RuntimeException('Malformed audit hash.');
        }
        return $value;
    }

    private function lp(string $value): string
    {
        $length = strlen($value);
        if ($length > 0xFFFF_FFFF) {
            throw new RuntimeException('Audit associated data is too large.');
        }
        return pack('N', $length) . $value;
    }
}
