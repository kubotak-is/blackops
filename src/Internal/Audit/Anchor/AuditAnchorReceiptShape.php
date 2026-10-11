<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final class AuditAnchorReceiptShape
{
    /** @param IntentValue $value */
    public static function assert(#[\SensitiveParameter] array $value): void
    {
        $state = $value['state'];
        if (in_array($state, ['prepared', 'externally_rejected'], strict: true)) {
            return;
        }
        if (!in_array(
            $state,
            ['externally_accepted', 'receipt_inputs_persisted', 'receipt_persisted', 'acknowledged'],
            strict: true,
        )) {
            throw new \RuntimeException();
        }
        $attempt = $value['attempt'];
        $updatedAt = $value['updatedAt'];
        $checkpointBytesHash = $value['checkpointBytesHash'];
        $expectedPredecessorVersion = $value['expectedPredecessorVersion'];
        $acceptedObjectVersion = $value['acceptedObjectVersion'];
        $leaseIsEmpty = [$value['leaseFencingToken'] === null, $value['leaseExpiresAt'] === null];
        $leaseExpiresAt = AuditAnchorTime::assertTimestamp($value['leaseExpiresAt'] ?? $updatedAt);
        $expectedObjectVersion = $expectedPredecessorVersion === null ? 1 : $expectedPredecessorVersion + 1;
        $commonValid = [
            $attempt >= 1,
            [
                $value['acceptedObjectVersion'] !== null,
                $value['acceptedObjectHash'] !== null,
                $value['acceptedAnchoredAt'] !== null,
                $value['anchorReceiptId'] !== null,
                $value['segmentId'] !== null,
            ] === [true, true, true, true, true],
            [
                $value['externalCallPhase'],
                $value['preCallClaimMode'],
                $value['preCallLookupStatus'],
                $value['nextAvailableAt'],
                $value['terminalReasonCode'],
            ] === [null, null, null, null, null],
            $leaseIsEmpty[0] === $leaseIsEmpty[1],
            [$state !== 'acknowledged', $leaseIsEmpty === [true, true]] !== [false, false],
            AuditAnchorTime::compare($updatedAt, $leaseExpiresAt) <= 0,
            $expectedObjectVersion === $acceptedObjectVersion,
            ($expectedPredecessorVersion === null) === ($value['previousAnchorReceiptHash'] === null),
        ];
        if (in_array(false, $commonValid, strict: true)) {
            throw new \RuntimeException();
        }
        AuditAnchorIntentShape::assertHash($value['acceptedObjectHash']);
        $acceptedObjectHash = AuditAnchorIntentShape::assertText($value['acceptedObjectHash']);
        if (!hash_equals($acceptedObjectHash, $checkpointBytesHash)) {
            throw new \RuntimeException();
        }
        if ($state === 'externally_accepted') {
            if (
                [
                    $value['receiptSigningKeyRef'],
                    $value['receiptSignedAt'],
                    $value['receiptPreimageHash'],
                    $value['receiptBytesHash'],
                    $value['anchorReceiptHash'],
                    $value['acknowledgedAt'],
                ] !== [null, null, null, null, null, null]
            ) {
                throw new \RuntimeException();
            }
            return;
        }
        $signingKeyRef = AuditAnchorIntentShape::assertText($value['receiptSigningKeyRef']);
        $acceptedAnchoredAt = AuditAnchorTime::assertTimestamp($value['acceptedAnchoredAt']);
        $receiptSignedAt = AuditAnchorTime::assertTimestamp($value['receiptSignedAt']);
        if (
            [
                AuditAnchorTime::compare($acceptedAnchoredAt, $receiptSignedAt) <= 0,
                AuditAnchorTime::compare($receiptSignedAt, $updatedAt) <= 0,
            ] !== [true, true]
        ) {
            throw new \RuntimeException();
        }
        AuditAnchorReceiptBody::assertPersistedHashes($value, $signingKeyRef, $acceptedAnchoredAt, $receiptSignedAt);
        $stateFields = [
            'receipt_inputs_persisted' => [$value['anchorReceiptHash'] === null, $value['acknowledgedAt'] === null],
            'receipt_persisted' => [$value['anchorReceiptHash'] !== null, $value['acknowledgedAt'] === null],
            'acknowledged' => [$value['anchorReceiptHash'] !== null, $value['acknowledgedAt'] !== null],
        ][$state] ?? [false, false];
        if ($stateFields !== [true, true]) {
            throw new \RuntimeException();
        }
        $acknowledgedAt = AuditAnchorTime::assertTimestamp($value['acknowledgedAt'] ?? $updatedAt);
        if (
            $value['acknowledgedAt'] !== null
            && [
                AuditAnchorTime::compare($receiptSignedAt, $acknowledgedAt) <= 0,
                AuditAnchorTime::compare($acknowledgedAt, $updatedAt) === 0,
            ] !== [true, true]
        ) {
            throw new \RuntimeException();
        }
    }

    public static function assertPublicReceiptHash(#[\SensitiveParameter] AuditAnchorIntent $intent): void
    {
        AuditAnchorReceiptBody::assertPublicReceiptHash($intent);
    }
}
