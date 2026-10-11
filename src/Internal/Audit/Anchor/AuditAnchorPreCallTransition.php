<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/**
 * @phpstan-import-type IntentValue from AuditAnchorIntentShape
 */
final class AuditAnchorPreCallTransition
{
    public function __construct(
        private readonly AuditAnchorConfiguration $configuration,
    ) {}

    public function lookup(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        array $outcome,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $result = AuditAnchorExternalOutcome::lookup($outcome);
        $value = $frame->intent()->toArray();
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        $this->activePending(
            $frame,
            $value,
            $trustedNow,
            'pending',
            ['external_submission_claim', 'pre_call_retry_claim'],
        );
        if ($result['outcome'] === 'external_object_found') {
            /** @var array{anchorObjectRef:string,anchorObjectVersion:int,acceptedObjectHash:string,acceptedAnchoredAt:string,segmentId:string,previousAnchorReceiptHash:?string} $found */
            $found = $result['foundObject'];
            $binding = $frame->binding()->toArray();
            $foundObjectRef = $found['anchorObjectRef'];
            $foundObjectVersion = $found['anchorObjectVersion'];
            $foundObjectHash = $found['acceptedObjectHash'];
            $foundAcceptedAt = $found['acceptedAnchoredAt'];
            $foundSegmentId = $found['segmentId'];
            $expectedPredecessorVersion = $value['expectedPredecessorVersion'];
            $checkpointBytesHash = $value['checkpointBytesHash'];
            $foundPreviousReceiptHash = $found['previousAnchorReceiptHash'];
            if (
                [
                    hash_equals($foundObjectHash, $checkpointBytesHash),
                    [
                        $foundObjectRef,
                        $foundObjectVersion,
                        $foundSegmentId,
                    ] === [
                        $value['anchorObjectRef'],
                        ($expectedPredecessorVersion ?? 0) + 1,
                        $binding['segmentId'],
                    ],
                    hash_equals((string) $foundPreviousReceiptHash, (string) $binding['previousAnchorReceiptHash']),
                    AuditAnchorTime::compare($binding['checkpointSignedAt'], $foundAcceptedAt) <= 0,
                ] !== [true, true, true, true]
            ) {
                throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
            }
            return AuditAnchorTransitionGuard::advance(
                $frame,
                $expected,
                self::accepted(
                    $value,
                    [
                        'anchorObjectRef' => $foundObjectRef,
                        'anchorObjectVersion' => $foundObjectVersion,
                        'acceptedObjectHash' => $foundObjectHash,
                        'acceptedAnchoredAt' => $foundAcceptedAt,
                        'segmentId' => $foundSegmentId,
                        'previousAnchorReceiptHash' => $foundPreviousReceiptHash,
                    ],
                    $binding,
                ),
                $trustedNow,
            );
        }
        if ($result['outcome'] === 'external_object_absent') {
            /** @var array<string,mixed> $next */
            $next = $value;
            $next['preCallLookupStatus'] = 'absent';
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
        }
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        $next['preCallLookupStatus'] = null;
        $next['preCallClaimMode'] = 'retry_same_attempt';
        $next['nextAvailableAt'] = AuditAnchorTime::addSeconds($trustedNow, $this->configuration->retryDelaySeconds);
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    public function callStart(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $value = $frame->intent()->toArray();
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        $this->activePending(
            $frame,
            $value,
            $trustedNow,
            'absent',
            ['external_submission_claim', 'pre_call_retry_claim'],
        );
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['externalCallPhase'] = 'post_call_uncertain';
        $next['preCallClaimMode'] = null;
        $next['preCallLookupStatus'] = null;
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
        if (AuditAnchorTime::compare($trustedNow, $value['updatedAt']) < 0) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $trustedNow;
        if (
            [
                [$value['state'], $value['externalCallPhase']],
                in_array($value['preCallLookupStatus'], ['pending', 'absent'], strict: true),
                [$value['leaseFencingToken'] === null, $leaseExpiresAt === null],
                AuditAnchorTime::compare($trustedNow, $leaseExpiresAt) >= 0,
            ] !== [
                ['prepared', 'pre_call'],
                true,
                [false, false],
                true,
            ]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        $next['preCallLookupStatus'] = null;
        $next['preCallClaimMode'] = 'retry_same_attempt';
        $next['nextAvailableAt'] = AuditAnchorTime::addSeconds($trustedNow, $this->configuration->retryDelaySeconds);
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    /** @param IntentValue $value @param list<string> $operations */
    private function activePending(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $trustedNow,
        ?string $status,
        #[\SensitiveParameter]
        array $operations,
    ): void {
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $trustedNow;
        $originValue = $frame->leaseOrigin()?->toArray();
        AuditAnchorTime::assertTransitionTimestamp($trustedNow);
        if (AuditAnchorTime::compare($trustedNow, $value['updatedAt']) < 0) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        if (
            [
                [
                    $value['state'],
                    $value['externalCallPhase'],
                    $value['preCallLookupStatus'],
                    $value['leaseFencingToken'] === null,
                    $value['leaseExpiresAt'] === null,
                    $value['nextAvailableAt'],
                ],
                in_array($originValue['operation'] ?? '', $operations, strict: true),
                AuditAnchorTime::compare($trustedNow, $leaseExpiresAt) < 0,
            ] !== [
                ['prepared', 'pre_call', $status, false, false, null],
                true,
                true,
            ]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
    }

    /**
     * @param IntentValue $value
     * @param array{anchorObjectRef:string,anchorObjectVersion:int,acceptedObjectHash:string,acceptedAnchoredAt:string,segmentId:string,previousAnchorReceiptHash:?string} $found
     * @param array{segmentId:string,previousAnchorReceiptHash:?string,checkpointSignedAt:string,anchorReceiptId:string} $binding
     * @return array<string,mixed>
     */
    private static function accepted(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        array $found,
        #[\SensitiveParameter]
        array $binding,
    ): array {
        $next = $value;
        $next['state'] = 'externally_accepted';
        $next['acceptedObjectVersion'] = $found['anchorObjectVersion'];
        $next['acceptedObjectHash'] = $found['acceptedObjectHash'];
        $next['acceptedAnchoredAt'] = $found['acceptedAnchoredAt'];
        $next['anchorReceiptId'] = $binding['anchorReceiptId'];
        $next['segmentId'] = $binding['segmentId'];
        $next['previousAnchorReceiptHash'] = $binding['previousAnchorReceiptHash'];
        $next['externalCallPhase'] = null;
        $next['preCallClaimMode'] = null;
        $next['preCallLookupStatus'] = null;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        $next['nextAvailableAt'] = null;
        return $next;
    }
}
