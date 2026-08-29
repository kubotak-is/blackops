<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;

/**
 * Builds, encodes, and verifies the durable-only ingress wire before it is
 * passed to the protected-body codec.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final class AuditIngressEnvelopeCodec
{
    public const int MAX_BYTES = 1_048_576;

    public function __construct(
        private AuditRecordCodec $records,
        private AuditPartitionIdDeriver $partitions,
        private AuditJcsCodec $jcs,
    ) {}

    public function create(
        CanonicalAuditLogicalRecord $logical,
        int $ingressOrdinal,
        #[\SensitiveParameter]
        string $idempotencyKey,
    ): AuditIngressEnvelope {
        if ($ingressOrdinal < 1 || $ingressOrdinal > AuditJcsCodec::MAX_SAFE_INTEGER) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        AuditValidationException::assertSafeString($idempotencyKey, 1, 256);
        if ($logical->definition()->delivery !== AuditDelivery::Durable) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        $value = $logical->toArray();
        if (($value['policy']['auditDelivery'] ?? null) !== AuditDelivery::Durable->value) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        $tenant = $this->tenant($value['tenant'] ?? null);
        $partition = $this->partitions->derive($tenant);
        $contentHash = $this->records->contentHash($logical);
        $recordedAt = $value['time']['recordedAt'] ?? null;
        $recordId = $value['recordId'] ?? null;
        if (!is_string($recordedAt) || !is_string($recordId)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return new AuditIngressEnvelope(
            1,
            'audit_ingress_envelope',
            $value,
            $contentHash,
            $recordedAt,
            $ingressOrdinal,
            $idempotencyKey,
            $recordId,
            $partition,
            $tenant,
        );
    }

    public function encode(#[\SensitiveParameter] AuditIngressEnvelope $envelope): string
    {
        $bytes = $this->jcs->canonicalJson($envelope->toArray());
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        }
        return $bytes;
    }

    public function decode(#[\SensitiveParameter] string $bytes): AuditIngressEnvelope
    {
        $value = $this->decodeIngressValue($bytes);
        /** @var array<string,mixed> $logical */
        $logical = $value['logicalRecord'];
        $logical = $this->orderedLogical($logical);
        /** @var array<string,mixed> $logical */
        $tenant = $this->tenant($value['tenantBinding']);
        $logicalTenant = $this->tenant($logical['tenant'] ?? null);
        $computedPartition = $this->partitions->derive($logicalTenant);
        $valid = [
            $computedPartition === $value['partitionId'],
            $logicalTenant?->toArray() === $tenant?->toArray(),
            ($logical['recordId'] ?? null) === $value['recordId'],
            ($logical['time']['recordedAt'] ?? null) === $value['recordedAt'],
        ];
        if (in_array(false, $valid, strict: true)) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        AuditRecordTime::assertTimestamp($value['recordedAt']);
        AuditValidationException::assertSafeString($value['idempotencyKey'], 1, 256);
        if (($logical['policy']['auditDelivery'] ?? null) !== AuditDelivery::Durable->value) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        $computedContentHash = $this->records->contentHashFromSnapshot($logical);
        if ($computedContentHash !== $value['contentHash']) {
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        }
        $envelope = new AuditIngressEnvelope(
            $value['schemaVersion'],
            $value['kind'],
            $logical,
            $value['contentHash'],
            $value['recordedAt'],
            $value['ingressOrdinal'],
            $value['idempotencyKey'],
            $value['recordId'],
            $value['partitionId'],
            $tenant,
        );
        if ($this->encode($envelope) !== $bytes) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        return $envelope;
    }

    /**
     * @return array{
     *     schemaVersion:int,
     *     kind:string,
     *     logicalRecord:array<string,mixed>,
     *     contentHash:string,
     *     recordedAt:string,
     *     ingressOrdinal:int,
     *     idempotencyKey:string,
     *     recordId:string,
     *     partitionId:string,
     *     tenantBinding:mixed
     * }
     */
    private function decodeIngressValue(#[\SensitiveParameter] string $bytes): array
    {
        if (
            $bytes === ''
            || strlen($bytes) > self::MAX_BYTES
            || str_contains($bytes, "\n")
            || str_contains($bytes, "\r")
        ) {
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        }
        $this->jcs->deferUtf8Validation();
        $decoded = $this->jcs->decode($bytes);
        $value = $this->jcs->toNative($decoded);
        if (!is_array($value) || array_is_list($value)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var array<string,mixed> $value */
        if ($this->jcs->invalidUtf8Observed() || $this->jcs->unpairedSurrogateObserved()) {
            throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
        }
        $this->keys($value, [
            'schemaVersion',
            'kind',
            'logicalRecord',
            'contentHash',
            'recordedAt',
            'ingressOrdinal',
            'idempotencyKey',
            'recordId',
            'partitionId',
            'tenantBinding',
        ]);
        if (
            $value['schemaVersion'] !== 1
            || $value['kind'] !== 'audit_ingress_envelope'
            || !is_array($value['logicalRecord'])
            || array_is_list($value['logicalRecord'])
            || !is_string($value['contentHash'])
            || !is_string($value['recordedAt'])
            || !is_int($value['ingressOrdinal'])
            || !is_string($value['idempotencyKey'])
            || !is_string($value['recordId'])
            || !is_string($value['partitionId'])
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var array<string,mixed> $logicalRecord */
        $logicalRecord = $value['logicalRecord'];
        /** @var int $schemaVersion */
        $schemaVersion = $value['schemaVersion'];
        /** @var string $kind */
        $kind = $value['kind'];
        /** @var string $contentHash */
        $contentHash = $value['contentHash'];
        /** @var string $recordedAt */
        $recordedAt = $value['recordedAt'];
        /** @var int $ingressOrdinal */
        $ingressOrdinal = $value['ingressOrdinal'];
        /** @var string $idempotencyKey */
        $idempotencyKey = $value['idempotencyKey'];
        /** @var string $recordId */
        $recordId = $value['recordId'];
        /** @var string $partitionId */
        $partitionId = $value['partitionId'];
        return [
            'schemaVersion' => $schemaVersion,
            'kind' => $kind,
            'logicalRecord' => $logicalRecord,
            'contentHash' => $contentHash,
            'recordedAt' => $recordedAt,
            'ingressOrdinal' => $ingressOrdinal,
            'idempotencyKey' => $idempotencyKey,
            'recordId' => $recordId,
            'partitionId' => $partitionId,
            'tenantBinding' => $value['tenantBinding'],
        ];
    }

    public function idempotencyKeyHash(#[\SensitiveParameter] AuditIngressEnvelope $envelope): string
    {
        $value = $envelope->toArray();
        if (!is_string($value['partitionId'] ?? null)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return hash(
            'sha256',
            $this->stringEncoding('blackops.audit.outbox.idempotency.v1') . $this->stringEncoding($value['partitionId'])
                . $this->stringEncoding($envelope->idempotencyKey()),
        );
    }

    private function tenant(mixed $value): ?AuditTypedReference
    {
        if ($value === null) {
            return null;
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['type', 'id'] && array_keys($value) !== ['id', 'type']
            || !is_string($value['type'])
            || !is_string($value['id'])
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return new AuditTypedReference($value['type'], $value['id']);
    }

    private function stringEncoding(string $value): string
    {
        AuditValidationException::assertUtf8($value);
        return 's:' . strlen($value) . ':' . $value;
    }

    /** @param array<string,mixed> $value @param list<string> $keys @return array<string,mixed> */
    private function orderedLogical(array $value): array
    {
        $keys = [
            'schemaVersion',
            'kind',
            'recordId',
            'event',
            'action',
            'actor',
            'resource',
            'outcome',
            'policy',
            'tenant',
            'time',
            'source',
            'correlation',
            'evidence',
        ];
        if (array_diff($keys, array_keys($value)) !== [] || array_diff(array_keys($value), $keys) !== []) {
            return $value;
        }
        $ordered = [];
        foreach ($keys as $key) {
            $ordered[$key] = $value[$key];
        }
        return $this->reorderLogicalMembers($ordered);
    }

    /** @param array<string,mixed> $ordered @return array<string,mixed> */
    private function reorderLogicalMembers(array $ordered): array
    {
        foreach ([
            'event' => ['type', 'version', 'category', 'catalogHash'],
            'action' => ['id', 'type', 'phase'],
            'actor' => ['principal', 'execution'],
            'resource' => ['type', 'id'],
            'outcome' => ['status', 'code'],
            'policy' => ['id', 'version', 'decision', 'reasonCode', 'auditDelivery'],
            'time' => ['occurredAt', 'recordedAt'],
            'source' => ['owner', 'component', 'runtime', 'buildId'],
        ] as $name => $memberKeys) {
            $members = $ordered[$name] ?? null;
            if (
                !is_array($members)
                || array_diff($memberKeys, array_keys($members)) !== []
                || array_diff(array_keys($members), $memberKeys) !== []
            ) {
                continue;
            }
            /** @var array<string,mixed> $members */
            $ordered[$name] = $this->reorder($members, $memberKeys);
        }
        if (is_array($ordered['actor'] ?? null)) {
            $actor = $ordered['actor'];
            foreach (['principal', 'execution'] as $name) {
                $member = $actor[$name] ?? null;
                if (!is_array($member) || array_keys($member) !== ['id', 'type']) {
                    continue;
                }
                /** @var array<string,mixed> $member */
                $actor[$name] = $this->reorder($member, ['type', 'id']);
            }
            $ordered['actor'] = $actor;
        }
        $tenant = $ordered['tenant'] ?? null;
        if (is_array($tenant) && array_keys($tenant) === ['id', 'type']) {
            /** @var array<string,mixed> $tenant */
            $ordered['tenant'] = $this->reorder($tenant, ['type', 'id']);
        }
        $correlation = $ordered['correlation'] ?? null;
        if (is_array($correlation)) {
            $attempt = $correlation['attempt'] ?? null;
            if (is_array($attempt) && array_keys($attempt) === ['kind', 'number']) {
                /** @var array<string,mixed> $attempt */
                $correlation['attempt'] = $this->reorder($attempt, ['number', 'kind']);
                $ordered['correlation'] = $correlation;
            }
        }
        return $ordered;
    }

    /** @param array<string,mixed> $value @param list<string> $keys @return array<string,mixed> */
    private function reorder(array $value, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $value[$key];
        }
        return $result;
    }

    /** @param array<string,mixed> $value @param list<string> $keys */
    private function keys(array $value, array $keys): void
    {
        if (
            count($value) !== count($keys)
            || array_diff(array_keys($value), $keys) !== []
            || array_diff($keys, array_keys($value)) !== []
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }
}
