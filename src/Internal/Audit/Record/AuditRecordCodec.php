<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;

/**
 * Encodes and verifies the detached logical and complete AuditRecord wires.
 * The codec owns the hash preimages; the A2 validator owns logical semantics.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditRecordCodec
{
    public const int MAX_BYTES = 1_048_576;

    private const string CONTENT_DOMAIN = 'blackops.audit.content.v1';

    private const string RECORD_DOMAIN = 'blackops.audit.record.v1';

    private const string HASH_SUITE = 'audit-sha256-jcs-v1';

    private AuditPartitionIdDeriver $partitions;

    public function __construct(
        private AuditRecordValidator $validator,
        private AuditJcsCodec $jcs,
    ) {
        $this->partitions = new AuditPartitionIdDeriver($jcs);
    }

    public function logicalBytes(CanonicalAuditLogicalRecord $logical): string
    {
        $bytes = $this->jcs->canonicalJson($logical->toArray());
        $this->assertWithinLimit($bytes);
        return $bytes;
    }

    public function contentHash(CanonicalAuditLogicalRecord $logical): string
    {
        return $this->hashContentBytes($this->logicalBytes($logical));
    }

    /** @param array<string,mixed> $snapshot */
    public function contentHashFromSnapshot(array $snapshot): string
    {
        /** @var array<string,mixed> $snapshot */
        $snapshot = $this->orderedLogical($snapshot);
        $validated = $this->validateSnapshot($snapshot);
        return $this->hashContentBytes($this->canonicalLogicalBytes($validated->snapshot));
    }

    public function assertFutureCompleteCapacity(CanonicalAuditLogicalRecord $logical): void
    {
        $this->logicalBytes($logical);
        $logicalSnapshot = $logical->toArray();
        $partitionId = $this->partitionForSnapshot($logicalSnapshot);
        $position = new AuditChainPosition(
            $partitionId,
            'ffffffff-ffff-7fff-bfff-ffffffffffff',
            AuditJcsCodec::MAX_SAFE_INTEGER,
            str_repeat('f', times: 64),
        );
        $integrity = new AuditRecordIntegrity(
            self::HASH_SUITE,
            $position->partitionId,
            $position->segmentId,
            $position->sequence,
            $position->previousRecordHash,
            str_repeat('f', times: 64),
            str_repeat('f', times: 64),
        );
        $bytes = $this->jcs->canonicalJson([...$logicalSnapshot, 'integrity' => $integrity->toArray()]) . "\n";
        $this->assertWithinLimit($bytes);
    }

    public function complete(CanonicalAuditLogicalRecord $logical, AuditChainPosition $position): CompleteAuditRecord
    {
        $logicalSnapshot = $logical->toArray();
        $this->assertPartitionBinding($logicalSnapshot, $position->partitionId);
        $logicalBytes = $this->logicalBytes($logical);
        $contentHash = $this->hashContentBytes($logicalBytes);
        $recordHash = $this->hashRecord($position, $contentHash);
        $integrity = new AuditRecordIntegrity(
            self::HASH_SUITE,
            $position->partitionId,
            $position->segmentId,
            $position->sequence,
            $position->previousRecordHash,
            $contentHash,
            $recordHash,
        );
        $complete = [...$logicalSnapshot, 'integrity' => $integrity->toArray()];
        $bytes = $this->jcs->canonicalJson($complete) . "\n";
        $this->assertWithinLimit($bytes);
        return new CompleteAuditRecord($logicalSnapshot, $logical->definition(), $integrity, $bytes);
    }

    public function decode(#[\SensitiveParameter] string $bytes): CompleteAuditRecord
    {
        $value = $this->decodeCompleteValue($bytes);
        $integrityValue = $this->decodeIntegrityValue($value);
        $logical = $value;
        unset($logical['integrity']);
        /** @var array<string,mixed> $logical */
        $logical = $this->orderedLogical($logical);
        $validated = $this->validateSnapshot($logical);
        $integrity = $this->integrity($integrityValue);
        $this->assertPartitionBinding($validated->snapshot, $integrity->partitionId);
        $expectedContentHash = $this->hashContentBytes($this->canonicalLogicalBytes($validated->snapshot));
        $this->assertContentHash($expectedContentHash, $integrityValue['contentHash']);
        $position = new AuditChainPosition(
            $integrity->partitionId,
            $integrity->segmentId,
            $integrity->sequence,
            $integrity->previousRecordHash,
        );
        $this->assertRecordHash($position, $expectedContentHash, $integrity->recordHash);
        $this->assertCanonicalBytes($validated->snapshot, $integrity, $bytes);
        return new CompleteAuditRecord($validated->snapshot, $validated->definition, $integrity, $bytes);
    }

    /** @return array<string,mixed> */
    private function decodeCompleteValue(#[\SensitiveParameter] string $bytes): array
    {
        if (
            $bytes === ''
            || !str_ends_with($bytes, "\n")
            || substr_count($bytes, needle: "\n") !== 1
            || str_contains($bytes, "\r")
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        $this->assertWithinLimit($bytes);
        $value = $this->decodeObject(substr($bytes, offset: 0, length: -1));
        $this->sameKeys($value, [
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
            'integrity',
        ]);
        return $value;
    }

    /**
     * @param array<string,mixed> $value
     * @return array{hashSuite:string,partitionId:string,segmentId:string,sequence:int,previousRecordHash:?string,contentHash:string,recordHash:string}
     */
    private function decodeIntegrityValue(array $value): array
    {
        $integrity = $value['integrity'];
        if (!is_array($integrity) || array_is_list($integrity)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var array<string,mixed> $integrity */
        $this->sameKeys($integrity, [
            'hashSuite',
            'partitionId',
            'segmentId',
            'sequence',
            'previousRecordHash',
            'contentHash',
            'recordHash',
        ]);
        $hashSuite = $integrity['hashSuite'];
        $partitionId = $integrity['partitionId'];
        $segmentId = $integrity['segmentId'];
        $sequence = $integrity['sequence'];
        $previousRecordHash = $integrity['previousRecordHash'];
        $contentHash = $integrity['contentHash'];
        $recordHash = $integrity['recordHash'];
        if (
            !is_string($hashSuite)
            || !is_string($partitionId)
            || !is_string($segmentId)
            || !is_int($sequence)
            || $previousRecordHash !== null && !is_string($previousRecordHash)
            || !is_string($contentHash)
            || !is_string($recordHash)
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return [
            'hashSuite' => $hashSuite,
            'partitionId' => $partitionId,
            'segmentId' => $segmentId,
            'sequence' => $sequence,
            'previousRecordHash' => $previousRecordHash,
            'contentHash' => $contentHash,
            'recordHash' => $recordHash,
        ];
    }

    private function assertContentHash(string $expected, string $actual): void
    {
        if ($expected !== $actual) {
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        }
    }

    private function assertRecordHash(AuditChainPosition $position, string $contentHash, string $actual): void
    {
        if ($this->hashRecord($position, $contentHash) !== $actual) {
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        }
    }

    /**
     * @param array{hashSuite:string,partitionId:string,segmentId:string,sequence:int,previousRecordHash:?string,contentHash:string,recordHash:string} $value
     */
    private function integrity(array $value): AuditRecordIntegrity
    {
        return new AuditRecordIntegrity(
            $value['hashSuite'],
            $value['partitionId'],
            $value['segmentId'],
            $value['sequence'],
            $value['previousRecordHash'],
            $value['contentHash'],
            $value['recordHash'],
        );
    }

    /** @param array<string,mixed> $logical */
    private function assertCanonicalBytes(array $logical, AuditRecordIntegrity $integrity, string $bytes): void
    {
        $expected = $this->jcs->canonicalJson([...$logical, 'integrity' => $integrity->toArray()]) . "\n";
        if ($expected !== $bytes) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
    }

    private function hashContentBytes(string $logicalBytes): string
    {
        return hash('sha256', $this->stringEncoding(self::CONTENT_DOMAIN) . $this->bytesEncoding($logicalBytes));
    }

    /** @param array<string,mixed> $snapshot */
    private function validateSnapshot(array $snapshot): ValidatedAuditLogicalRecord
    {
        return $this->validator->validateLogical(new AuditLogicalRecordCandidate($snapshot));
    }

    /** @param array<string,mixed> $snapshot */
    private function assertPartitionBinding(array $snapshot, string $partitionId): void
    {
        if ($this->partitionForSnapshot($snapshot) !== $partitionId) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    /** @param array<string,mixed> $snapshot */
    private function partitionForSnapshot(array $snapshot): string
    {
        $tenant = $snapshot['tenant'] ?? null;
        if ($tenant === null) {
            return $this->partitions->derive(null);
        }
        if (
            !is_array($tenant)
            || array_keys($tenant) !== ['type', 'id']
            || !is_string($tenant['type'])
            || !is_string($tenant['id'])
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return $this->partitions->derive(new AuditTypedReference($tenant['type'], $tenant['id']));
    }

    /** @param array<string,mixed> $snapshot */
    private function canonicalLogicalBytes(array $snapshot): string
    {
        $bytes = $this->jcs->canonicalJson($snapshot);
        $this->assertWithinLimit($bytes);
        return $bytes;
    }

    private function hashRecord(AuditChainPosition $position, string $contentHash): string
    {
        $content = $this->decodeHash($contentHash);
        $previous = $position->previousRecordHash === null
            ? str_repeat("\0", times: 32)
            : $this->decodeHash($position->previousRecordHash);
        return hash('sha256', implode('', [
            $this->stringEncoding(self::RECORD_DOMAIN),
            $this->stringEncoding($position->partitionId),
            $this->stringEncoding($position->segmentId),
            $this->integerEncoding($position->sequence),
            $this->bytesEncoding($previous),
            $this->bytesEncoding($content),
            $this->stringEncoding(self::HASH_SUITE),
        ]));
    }

    private function stringEncoding(string $value): string
    {
        AuditValidationException::assertUtf8($value);
        return 's:' . strlen($value) . ':' . $value;
    }

    private function bytesEncoding(string $value): string
    {
        return 'b:' . strlen($value) . ':' . $value;
    }

    private function integerEncoding(int $value): string
    {
        if ($value < 0 || $value > AuditJcsCodec::MAX_SAFE_INTEGER) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        return 'i:' . $value;
    }

    private function decodeHash(string $value): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        $decoded = hex2bin($value);
        if ($decoded === false) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    private function decodeObject(string $json): array
    {
        $this->jcs->deferUtf8Validation();
        $decoded = $this->jcs->decode($json);
        $value = $this->jcs->toNative($decoded);
        if (!is_array($value) || array_is_list($value)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        if ($this->jcs->invalidUtf8Observed() || $this->jcs->unpairedSurrogateObserved()) {
            throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
        }
        /** @var array<string,mixed> $value */
        return $value;
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private function orderedLogical(array $value): array
    {
        $top = [
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
        if (array_diff($top, array_keys($value)) !== [] || array_diff(array_keys($value), $top) !== []) {
            return $value;
        }
        $ordered = [];
        foreach ($top as $key) {
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
        ] as $name => $keys) {
            if (
                !is_array($ordered[$name])
                || array_diff($keys, array_keys($ordered[$name])) !== []
                || array_diff(array_keys($ordered[$name]), $keys) !== []
            ) {
                continue;
            }
            $ordered[$name] = $this->reorder($ordered[$name], $keys);
        }
        if (is_array($ordered['actor'] ?? null)) {
            foreach (['principal', 'execution'] as $name) {
                if (
                    !is_array($ordered['actor'][$name] ?? null)
                    || array_keys($ordered['actor'][$name]) !== ['id', 'type']
                ) {
                    continue;
                }
                $ordered['actor'][$name] = $this->reorder($ordered['actor'][$name], ['type', 'id']);
            }
        }
        if (is_array($ordered['tenant'] ?? null) && array_keys($ordered['tenant']) === ['id', 'type']) {
            $ordered['tenant'] = $this->reorder($ordered['tenant'], ['type', 'id']);
        }
        $correlation = $ordered['correlation'] ?? null;
        if (is_array($correlation)) {
            $attempt = $correlation['attempt'] ?? null;
            if (is_array($attempt) && array_keys($attempt) === ['kind', 'number']) {
                $correlation['attempt'] = $this->reorder($attempt, ['number', 'kind']);
                $ordered['correlation'] = $correlation;
            }
        }
        return $ordered;
    }

    /** @param array<array-key,mixed> $value @param list<string> $keys @return array<string,mixed> */
    private function reorder(array $value, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $value[$key];
        }
        return $result;
    }

    /** @param array<string,mixed> $value @param list<string> $keys */
    private function sameKeys(array $value, array $keys): void
    {
        if (
            count($value) !== count($keys)
            || array_diff(array_keys($value), $keys) !== []
            || array_diff($keys, array_keys($value)) !== []
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function assertWithinLimit(string $bytes): void
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        }
    }
}
