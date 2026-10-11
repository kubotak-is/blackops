<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final readonly class AuditAnchorIntent
{
    private \SensitiveParameterValue $snapshot;

    private function __construct(#[\SensitiveParameter] array $value)
    {
        $this->snapshot = new \SensitiveParameterValue($value);
    }

    /** @param array<array-key,mixed> $value */
    public static function fromArray(#[\SensitiveParameter] array $value): self
    {
        $normalized = AuditAnchorIntentShape::normalize($value);
        $intent = new self($normalized);
        AuditAnchorReceiptShape::assertPublicReceiptHash($intent);
        return $intent;
    }

    /**
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
    public function toArray(): array
    {
        /** @var IntentValue $value */
        $value = $this->snapshot->getValue();
        return [...$value];
    }

    public function state(): AuditAnchorState
    {
        $value = $this->toArray();
        return AuditAnchorState::from($value['state']);
    }

    public function version(): int
    {
        $value = $this->toArray();
        return $value['version'];
    }

    /** @param IntentValue $current @param array<string,mixed> $candidate */
    public static function assertTransitionImmutable(
        #[\SensitiveParameter]
        array $current,
        #[\SensitiveParameter]
        array $candidate,
    ): void {
        $receiptInputInstall = [$current['state'], $candidate['state']] === [
            'externally_accepted',
            'receipt_inputs_persisted',
        ];
        $receiptPersistInstall = [$current['state'], $candidate['state']] === [
            'receipt_inputs_persisted',
            'receipt_persisted',
        ];
        $accepting = [$current['state'], $candidate['state']] === ['prepared', 'externally_accepted'];
        $immutable = [
            'schemaVersion',
            'kind',
            'intentId',
            'partitionId',
            'checkpointId',
            'anchorStoreRef',
            'anchorObjectRef',
            'expectedPredecessorVersion',
            'conditionalCreateToken',
            ...($receiptInputInstall ? [] : ['receiptSigningKeyRef', 'receiptSignedAt']),
            ...(
                $accepting
                    ? []
                    : [
                        'acceptedObjectVersion',
                        'acceptedAnchoredAt',
                        'anchorReceiptId',
                        'segmentId',
                    ]
            ),
        ];
        if (
            array_diff($immutable, array_keys($candidate)) !== []
            || array_filter($immutable, static fn(string $field): bool => $candidate[$field] !== $current[$field])
                !== []
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        $hashes = [
            'checkpointHash',
            'checkpointBytesHash',
            'expectedPredecessorHash',
            'idempotencyKeyHash',
            ...($receiptInputInstall ? [] : ['receiptPreimageHash', 'receiptBytesHash']),
            ...($receiptPersistInstall ? [] : ['anchorReceiptHash']),
            ...(
                $accepting
                    ? []
                    : [
                        'acceptedObjectHash',
                        'previousAnchorReceiptHash',
                    ]
            ),
        ];
        if (
            array_diff($hashes, array_keys($candidate)) !== []
            || array_filter(
                $hashes,
                static fn(string $field): bool => (
                    [
                        $current[$field] === null,
                        hash_equals((string) $current[$field], (string) $candidate[$field]),
                    ] !== [$candidate[$field] === null, true]
                ),
            ) !== []
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        if (
            $receiptInputInstall
            && [
                $current['receiptSigningKeyRef'],
                $current['receiptSignedAt'],
                $current['receiptPreimageHash'],
                $current['receiptBytesHash'],
                $candidate['receiptSigningKeyRef'] !== null,
                $candidate['receiptSignedAt'] !== null,
                $candidate['receiptPreimageHash'] !== null,
                $candidate['receiptBytesHash'] !== null,
            ] !== [null, null, null, null, true, true, true, true]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        if (
            $receiptPersistInstall
            && [
                $current['anchorReceiptHash'],
                $candidate['anchorReceiptHash'] !== null,
            ] !== [null, true]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
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
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
    }
}
