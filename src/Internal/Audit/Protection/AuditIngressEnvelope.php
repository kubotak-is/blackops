<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use RuntimeException;

/**
 * Detached durable-ingress wire. Its raw idempotency key is intentionally
 * available only to the outbox codec that derives the clear hash.
 *
 * @mago-expect lint:excessive-parameter-list
 */
final class AuditIngressEnvelope
{
    private \SensitiveParameterValue $snapshot;

    /** @param array<array-key,mixed> $logicalRecord */
    public function __construct(
        int $schemaVersion,
        string $kind,
        #[\SensitiveParameter]
        array $logicalRecord,
        string $contentHash,
        string $recordedAt,
        int $ingressOrdinal,
        #[\SensitiveParameter]
        string $idempotencyKey,
        string $recordId,
        string $partitionId,
        ?AuditTypedReference $tenantBinding,
    ) {
        self::assertShape($schemaVersion, $kind, $logicalRecord);
        self::assertBindings($logicalRecord, $recordId, $recordedAt, $tenantBinding);
        self::assertField(
            actual: (int) preg_match('/^[a-f0-9]{64}$/D', $contentHash),
            expected: 1,
            failure: AuditValidationFailure::GrammarInvalid,
        );
        AuditRecordTime::assertTimestamp($recordedAt);
        self::assertField(
            actual: max(1, min(AuditValidationException::MAX_SAFE_INTEGER, $ingressOrdinal)),
            expected: $ingressOrdinal,
            failure: AuditValidationFailure::SemanticInvalid,
        );
        AuditValidationException::assertSafeString($idempotencyKey, 1, 256);
        self::assertField(
            actual: (int) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
                $recordId,
            ),
            expected: 1,
            failure: AuditValidationFailure::GrammarInvalid,
        );
        self::assertField(
            actual: (int) preg_match('/^(?:system:global|tenant:[a-f0-9]{64})$/D', $partitionId),
            expected: 1,
            failure: AuditValidationFailure::GrammarInvalid,
        );
        $this->snapshot = new \SensitiveParameterValue([
            'schemaVersion' => $schemaVersion,
            'kind' => $kind,
            'logicalRecord' => $logicalRecord,
            'contentHash' => $contentHash,
            'recordedAt' => $recordedAt,
            'ingressOrdinal' => $ingressOrdinal,
            'idempotencyKey' => $idempotencyKey,
            'recordId' => $recordId,
            'partitionId' => $partitionId,
            'tenantBinding' => $tenantBinding?->toArray(),
        ]);
    }

    /** @param array<array-key,mixed> $logicalRecord */
    private static function assertShape(int $schemaVersion, string $kind, array $logicalRecord): void
    {
        if (
            [$schemaVersion, $kind, array_keys($logicalRecord)] !== [
                1,
                'audit_ingress_envelope',
                [
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
                ],
            ]
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    /** @param array<array-key,mixed> $logicalRecord */
    private static function assertBindings(
        array $logicalRecord,
        string $recordId,
        string $recordedAt,
        ?AuditTypedReference $tenantBinding,
    ): void {
        $policy = array_replace(['auditDelivery' => null], (array) $logicalRecord['policy']);
        $time = array_replace(['recordedAt' => null], (array) $logicalRecord['time']);
        $tenant = $tenantBinding?->toArray();
        $valid = [
            is_array($logicalRecord['policy']),
            $policy['auditDelivery'] === AuditDelivery::Durable->value,
            is_array($logicalRecord['time']),
            $logicalRecord['recordId'] === $recordId,
            $time['recordedAt'] === $recordedAt,
            $logicalRecord['tenant'] === $tenant,
        ];
        if (in_array(false, $valid, strict: true)) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    private static function assertField(int $actual, int $expected, AuditValidationFailure $failure): void
    {
        if ($actual !== $expected) {
            throw new AuditValidationException($failure);
        }
    }

    /**
     * @return array<string,mixed>
     * @mago-expect lint:inline-variable-return
     */
    public function toArray(): array
    {
        $value = $this->snapshot->getValue();
        if (!is_array($value)) {
            throw new RuntimeException('Ingress envelope is unavailable.');
        }
        /** @var array<string,mixed> $copy */
        $copy = self::copy($value);
        return $copy;
    }

    public function idempotencyKey(): string
    {
        $value = $this->snapshot->getValue();
        if (!is_array($value) || !is_string($value['idempotencyKey'] ?? null)) {
            throw new RuntimeException('Ingress envelope is unavailable.');
        }
        return $value['idempotencyKey'];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new RuntimeException('Ingress envelope unserialization is unavailable.');
    }

    private static function copy(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $copy = [];
        foreach ($value as $key => $member) {
            $copy[$key] = self::copy($member);
        }
        return $copy;
    }
}
