<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\AuditJcsCodec;

/**
 * @phpstan-type IntentValue array{
 *     schemaVersion:int,kind:string,intentId:string,state:string,partitionId:string,
 *     checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,
 *     anchorObjectRef:string,expectedPredecessorVersion:?int,expectedPredecessorHash:?string,
 *     conditionalCreateToken:string,idempotencyKeyHash:string,acceptedObjectVersion:?int,
 *     acceptedObjectHash:?string,acceptedAnchoredAt:?string,anchorReceiptId:?string,segmentId:?string,
 *     previousAnchorReceiptHash:?string,receiptSigningKeyRef:?string,receiptSignedAt:?string,
 *     receiptPreimageHash:?string,receiptBytesHash:?string,anchorReceiptHash:?string,
 *     externalCallPhase:?string,preCallClaimMode:?string,preCallLookupStatus:?string,
 *     leaseFencingToken:?string,leaseExpiresAt:?string,attempt:int,nextAvailableAt:?string,
 *     terminalReasonCode:?string,version:int,previousStateHash:?string,stateHash:string,
 *     updatedAt:string,acknowledgedAt:?string
 * }
 */
final class AuditAnchorIntentShape
{
    /** @var list<string> */
    public const array FIELDS = [
        'schemaVersion',
        'kind',
        'intentId',
        'state',
        'partitionId',
        'checkpointId',
        'checkpointHash',
        'checkpointBytesHash',
        'anchorStoreRef',
        'anchorObjectRef',
        'expectedPredecessorVersion',
        'expectedPredecessorHash',
        'conditionalCreateToken',
        'idempotencyKeyHash',
        'acceptedObjectVersion',
        'acceptedObjectHash',
        'acceptedAnchoredAt',
        'anchorReceiptId',
        'segmentId',
        'previousAnchorReceiptHash',
        'receiptSigningKeyRef',
        'receiptSignedAt',
        'receiptPreimageHash',
        'receiptBytesHash',
        'anchorReceiptHash',
        'externalCallPhase',
        'preCallClaimMode',
        'preCallLookupStatus',
        'leaseFencingToken',
        'leaseExpiresAt',
        'attempt',
        'nextAvailableAt',
        'terminalReasonCode',
        'version',
        'previousStateHash',
        'stateHash',
        'updatedAt',
        'acknowledgedAt',
    ];

    /**
     * @param array<array-key,mixed> $value
     * @return array{
     *     schemaVersion:int,kind:string,intentId:string,state:string,partitionId:string,
     *     checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,
     *     anchorObjectRef:string,expectedPredecessorVersion:?int,expectedPredecessorHash:?string,
     *     conditionalCreateToken:string,idempotencyKeyHash:string,acceptedObjectVersion:?int,
     *     acceptedObjectHash:?string,acceptedAnchoredAt:?string,anchorReceiptId:?string,segmentId:?string,
     *     previousAnchorReceiptHash:?string,receiptSigningKeyRef:?string,receiptSignedAt:?string,
     *     receiptPreimageHash:?string,receiptBytesHash:?string,anchorReceiptHash:?string,
     *     externalCallPhase:?string,preCallClaimMode:?string,preCallLookupStatus:?string,
     *     leaseFencingToken:?string,leaseExpiresAt:?string,attempt:int,nextAvailableAt:?string,
     *     terminalReasonCode:?string,version:int,previousStateHash:?string,stateHash:string,
     *     updatedAt:string,acknowledgedAt:?string
     * }
     */
    public static function normalize(#[\SensitiveParameter] array $value): array
    {
        try {
            self::assertClosed($value, self::FIELDS);
            $normalized = [];
            foreach (self::FIELDS as $field) {
                $normalized[$field] = $value[$field];
            }
            /** @var IntentValue $normalized */
            if ([$normalized['schemaVersion'], $normalized['kind']] !== [1, 'audit_anchor_intent']) {
                throw new \RuntimeException();
            }
            self::assertText($normalized['intentId']);
            $state = self::assertText($normalized['state']);
            if (AuditAnchorState::tryFrom($state) === null) {
                throw new \RuntimeException();
            }
            self::assertPartition($normalized['partitionId']);
            self::assertText($normalized['checkpointId']);
            self::assertHash($normalized['checkpointHash']);
            self::assertHash($normalized['checkpointBytesHash']);
            self::assertText($normalized['anchorStoreRef']);
            self::assertText($normalized['anchorObjectRef']);
            AuditAnchorScalar::nullableInteger(
                $normalized['expectedPredecessorVersion'],
                1,
                AuditJcsCodec::MAX_SAFE_INTEGER,
            );
            self::assertHash($normalized['expectedPredecessorHash'], 'nullable');
            if (
                ($normalized['expectedPredecessorVersion'] === null)
                !== ($normalized['expectedPredecessorHash'] === null)
            ) {
                throw new \RuntimeException();
            }
            self::assertText($normalized['conditionalCreateToken']);
            self::assertHash($normalized['idempotencyKeyHash']);
            AuditAnchorScalar::nullableInteger(
                $normalized['acceptedObjectVersion'],
                1,
                AuditJcsCodec::MAX_SAFE_INTEGER,
            );
            self::assertHash($normalized['acceptedObjectHash'], 'nullable');
            AuditAnchorScalar::nullableTimestamp($normalized['acceptedAnchoredAt']);
            AuditAnchorScalar::nullableText($normalized['anchorReceiptId']);
            $segment = AuditAnchorScalar::nullableText($normalized['segmentId']);
            if (
                $segment !== null
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $segment)
                    !== 1
            ) {
                throw new \RuntimeException();
            }
            self::assertHash($normalized['previousAnchorReceiptHash'], 'nullable');
            AuditAnchorScalar::nullableText($normalized['receiptSigningKeyRef']);
            AuditAnchorScalar::nullableTimestamp($normalized['receiptSignedAt']);
            self::assertHash($normalized['receiptPreimageHash'], 'nullable');
            self::assertHash($normalized['receiptBytesHash'], 'nullable');
            self::assertHash($normalized['anchorReceiptHash'], 'nullable');
            AuditAnchorScalar::nullableAllowed($normalized['externalCallPhase'], ['pre_call', 'post_call_uncertain']);
            AuditAnchorScalar::nullableAllowed($normalized['preCallClaimMode'], ['new_attempt', 'retry_same_attempt']);
            AuditAnchorScalar::nullableAllowed($normalized['preCallLookupStatus'], ['pending', 'absent']);
            AuditAnchorScalar::nullableText($normalized['leaseFencingToken']);
            AuditAnchorScalar::nullableTimestamp($normalized['leaseExpiresAt']);
            AuditAnchorScalar::integer($normalized['attempt'], 0, 32);
            AuditAnchorScalar::nullableTimestamp($normalized['nextAvailableAt']);
            AuditAnchorScalar::nullableAllowed($normalized['terminalReasonCode'], [
                'predecessor_mismatch',
                'permanent_rejection',
                'attempt_exhausted',
            ]);
            AuditAnchorScalar::integer($normalized['version'], 1, AuditJcsCodec::MAX_SAFE_INTEGER);
            self::assertHash($normalized['previousStateHash'], 'nullable');
            self::assertHash($normalized['stateHash']);
            AuditAnchorTime::assertTimestamp($normalized['updatedAt']);
            AuditAnchorScalar::nullableTimestamp($normalized['acknowledgedAt']);
            $leaseValid = [
                [$normalized['leaseFencingToken'] === null, $normalized['leaseExpiresAt'] === null] === [true, true],
                [$normalized['leaseFencingToken'] !== null, $normalized['leaseExpiresAt'] !== null] === [true, true],
            ] !== [false, false];
            $chainValid = [
                [$normalized['version'], $normalized['previousStateHash'] === null] === [1, true],
                [$normalized['version'] > 1, $normalized['previousStateHash'] !== null] === [true, true],
            ] !== [false, false];
            if ([$leaseValid, $chainValid] !== [true, true]) {
                throw new \RuntimeException();
            }
            AuditAnchorPreparedShape::assert($normalized);
            AuditAnchorReceiptShape::assert($normalized);
            $expectedHash = AuditAnchorCanonical::stateHash($normalized);
            $stateHash = self::assertText($normalized['stateHash']);
            if (!hash_equals($expectedHash, $stateHash)) {
                throw new \RuntimeException();
            }
            return $normalized;
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @param array<array-key,mixed> $value @param list<string> $keys */
    public static function assertClosed(#[\SensitiveParameter] array $value, #[\SensitiveParameter] array $keys): void
    {
        if (count($value) !== count($keys)) {
            throw new \RuntimeException();
        }
        $actual = array_keys($value);
        if (in_array(
            true,
            [
                array_is_list($value),
                array_filter($actual, static fn(mixed $key): bool => !is_string($key)) !== [],
                array_diff($actual, $keys) !== [],
                array_diff($keys, $actual) !== [],
            ],
            strict: true,
        )) {
            throw new \RuntimeException();
        }
    }

    public static function assertText(#[\SensitiveParameter] mixed $value, int $minimum = 1, int $maximum = 256): string
    {
        return AuditAnchorScalar::text($value, $minimum, $maximum);
    }

    public static function assertHash(#[\SensitiveParameter] mixed $value, string $mode = 'required'): ?string
    {
        return AuditAnchorScalar::hash($value, $mode);
    }

    public static function assertPartition(#[\SensitiveParameter] mixed $value): string
    {
        $partition = self::assertText($value, 1, 71);
        if (preg_match('/^(?:system:global|tenant:[a-f0-9]{64})$/D', $partition) !== 1) {
            throw new \RuntimeException();
        }
        return $partition;
    }
}
