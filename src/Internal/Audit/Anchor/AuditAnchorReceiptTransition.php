<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final class AuditAnchorReceiptTransition
{
    public function __construct(
        private readonly AuditAnchorConfiguration $configuration,
    ) {}

    public function inputClaim(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'receipt_input_claim');
    }

    public function persistClaim(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'receipt_persist_claim');
    }

    public function acknowledgementClaim(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'acknowledgement_claim');
    }

    public function inputPersist(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        try {
            AuditAnchorIntentShape::assertText($signingKeyRef);
            $value = $frame->intent()->toArray();
            $this->active($frame, $expected, $value, $trustedNow, 'receipt_input_claim');
            $receipt = AuditAnchorReceiptBody::build($frame->intent(), $signingKeyRef, $trustedNow);
            /** @var array<string,mixed> $next */
            $next = $value;
            $next['state'] = 'receipt_inputs_persisted';
            $next['receiptSigningKeyRef'] = $signingKeyRef;
            $next['receiptSignedAt'] = $trustedNow;
            $next['receiptPreimageHash'] = $receipt['receiptPreimageHash'];
            $next['receiptBytesHash'] = $receipt['receiptBytesHash'];
            $next['leaseFencingToken'] = null;
            $next['leaseExpiresAt'] = null;
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    public function persist(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $anchorReceiptHash,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        try {
            AuditAnchorIntentShape::assertHash($anchorReceiptHash);
            $value = $frame->intent()->toArray();
            $this->active($frame, $expected, $value, $trustedNow, 'receipt_persist_claim');
            $signingKeyRef = AuditAnchorIntentShape::assertText($value['receiptSigningKeyRef']);
            $receiptSignedAt = AuditAnchorTime::assertTimestamp($value['receiptSignedAt']);
            $receipt = AuditAnchorReceiptBody::build($frame->intent(), $signingKeyRef, $receiptSignedAt);
            if (!hash_equals($receipt['anchorReceiptHash'], $anchorReceiptHash)) {
                throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
            }
            /** @var array<string,mixed> $next */
            $next = $value;
            $next['state'] = 'receipt_persisted';
            $next['anchorReceiptHash'] = $anchorReceiptHash;
            $next['leaseFencingToken'] = null;
            $next['leaseExpiresAt'] = null;
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    public function acknowledge(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $value = $frame->intent()->toArray();
        $this->active($frame, $expected, $value, $trustedNow, 'acknowledgement_claim');
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['state'] = 'acknowledged';
        $next['acknowledgedAt'] = $trustedNow;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    public function reclaim(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $value = $frame->intent()->toArray();
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        AuditAnchorTime::assertTransitionTimestamp($trustedNow);
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $trustedNow;
        if (
            [
                in_array(
                    $value['state'],
                    ['externally_accepted', 'receipt_inputs_persisted', 'receipt_persisted'],
                    strict: true,
                ),
                [$value['leaseFencingToken'] === null, $value['leaseExpiresAt'] === null],
                AuditAnchorTime::compare($trustedNow, $leaseExpiresAt) >= 0,
            ] !== [true, [false, false], true]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    private function claim(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
        string $operation,
    ): AuditAnchorFrame {
        try {
            AuditAnchorIntentShape::assertText($newToken);
            $value = $frame->intent()->toArray();
            AuditAnchorTransitionGuard::assertExpected($frame, $expected);
            $state = [
                'receipt_input_claim' => 'externally_accepted',
                'receipt_persist_claim' => 'receipt_inputs_persisted',
                'acknowledgement_claim' => 'receipt_persisted',
            ][$operation];
            if (
                [
                    $value['state'],
                    $value['externalCallPhase'],
                    $value['preCallClaimMode'],
                    $value['preCallLookupStatus'],
                    $value['leaseFencingToken'] === null,
                    $value['nextAvailableAt'],
                ] !== [$state, null, null, null, true, null]
            ) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
            AuditAnchorTime::assertTransitionTimestamp($trustedNow);
            if (AuditAnchorTime::compare($trustedNow, $value['updatedAt']) < 0) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
            /** @var array<string,mixed> $next */
            $next = $value;
            $next['leaseFencingToken'] = $newToken;
            $next['leaseExpiresAt'] = AuditAnchorTime::addSeconds(
                $trustedNow,
                $this->configuration->leaseDurationSeconds,
            );
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow, $operation);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @param IntentValue $value */
    private function active(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $trustedNow,
        string $operation,
    ): void {
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        $expectedState = [
            'receipt_input_claim' => 'externally_accepted',
            'receipt_persist_claim' => 'receipt_inputs_persisted',
            'acknowledgement_claim' => 'receipt_persisted',
        ][$operation];
        $originValue = $frame->leaseOrigin()?->toArray();
        AuditAnchorTime::assertTransitionTimestamp($trustedNow);
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $trustedNow;
        if (
            AuditAnchorTime::compare($trustedNow, $value['updatedAt']) < 0
            || [
                [$value['state'], $value['leaseFencingToken'] === null, $value['leaseExpiresAt'] === null],
                $originValue['operation'] ?? '',
                AuditAnchorTime::compare($trustedNow, $leaseExpiresAt) < 0,
            ] !== [
                [$expectedState, false, false],
                $operation,
                true,
            ]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
    }
}
