<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final class AuditAnchorPostCallTransition
{
    public function __construct(
        private readonly AuditAnchorConfiguration $configuration,
    ) {}

    public function result(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        array $outcome,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $result = AuditAnchorExternalOutcome::result($outcome);
        $value = $frame->intent()->toArray();
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        $this->active($frame, $value, $trustedNow, 'active', ['external_submission_claim', 'pre_call_retry_claim']);
        if ($result['attempt'] !== $value['attempt']) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        if ($result['status'] === 'unavailable') {
            /** @var array<string,mixed> $next */
            $next = array_replace($value, [
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'nextAvailableAt' => AuditAnchorTime::addSeconds($trustedNow, $this->configuration->retryDelaySeconds),
            ]);
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
        }
        $binding = $frame->binding()->toArray();
        /** @var int $acceptedObjectVersion */
        $acceptedObjectVersion = $result['acceptedObjectVersion'];
        /** @var string $acceptedObjectHash */
        $acceptedObjectHash = $result['acceptedObjectHash'];
        /** @var string $acceptedAnchoredAt */
        $acceptedAnchoredAt = $result['acceptedAnchoredAt'];
        /** @var 'accepted'|'rejected' $status */
        $status = $result['status'];
        /** @var array<string,mixed> $next */
        $next = array_replace(
            $value,
            [
                'accepted' => [
                    'state' => 'externally_accepted',
                    'acceptedObjectVersion' => $acceptedObjectVersion,
                    'acceptedObjectHash' => $acceptedObjectHash,
                    'acceptedAnchoredAt' => $acceptedAnchoredAt,
                    'anchorReceiptId' => $binding['anchorReceiptId'],
                    'segmentId' => $binding['segmentId'],
                    'previousAnchorReceiptHash' => $binding['previousAnchorReceiptHash'],
                    'externalCallPhase' => null,
                    'preCallClaimMode' => null,
                    'preCallLookupStatus' => null,
                    'leaseFencingToken' => null,
                    'leaseExpiresAt' => null,
                    'nextAvailableAt' => null,
                ],
                'rejected' => [
                    'state' => 'externally_rejected',
                    'acceptedObjectVersion' => null,
                    'acceptedObjectHash' => null,
                    'acceptedAnchoredAt' => null,
                    'anchorReceiptId' => null,
                    'segmentId' => null,
                    'previousAnchorReceiptHash' => null,
                    'terminalReasonCode' => $result['reasonCode'],
                    'externalCallPhase' => null,
                    'preCallClaimMode' => null,
                    'preCallLookupStatus' => null,
                    'leaseFencingToken' => null,
                    'leaseExpiresAt' => null,
                    'nextAvailableAt' => null,
                ],
            ][$status],
        );
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    public function reconcile(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        array $outcome,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        $result = AuditAnchorExternalOutcome::reconciliation($outcome);
        $value = $frame->intent()->toArray();
        AuditAnchorTransitionGuard::assertExpected($frame, $expected);
        $this->active($frame, $value, $trustedNow, 'active', ['external_reconciliation_claim']);
        if ($result['outcome'] === 'external_object_found') {
            /** @var array{anchorObjectRef:string,anchorObjectVersion:int,acceptedObjectHash:string,acceptedAnchoredAt:string,segmentId:string,previousAnchorReceiptHash:?string} $found */
            $found = $result['foundObject'];
            $binding = $frame->binding()->toArray();
            $foundObjectRef = $found['anchorObjectRef'];
            $foundObjectVersion = $found['anchorObjectVersion'];
            $foundObjectHash = $found['acceptedObjectHash'];
            $foundAcceptedAt = $found['acceptedAnchoredAt'];
            $foundSegmentId = $found['segmentId'];
            $foundPreviousReceiptHash = $found['previousAnchorReceiptHash'];
            $expectedPredecessorVersion = $value['expectedPredecessorVersion'];
            $checkpointBytesHash = $value['checkpointBytesHash'];
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
                        'anchorObjectVersion' => $foundObjectVersion,
                        'acceptedObjectHash' => $foundObjectHash,
                        'acceptedAnchoredAt' => $foundAcceptedAt,
                    ],
                    $binding,
                ),
                $trustedNow,
            );
        }
        if ($value['attempt'] === 32 && $result['outcome'] === 'external_object_absent') {
            /** @var array<string,mixed> $terminal */
            $terminal = array_replace($value, [
                'state' => 'externally_rejected',
                'terminalReasonCode' => 'attempt_exhausted',
                'externalCallPhase' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'nextAvailableAt' => null,
            ]);
            return AuditAnchorTransitionGuard::advance($frame, $expected, $terminal, $trustedNow);
        }
        $retryAt = AuditAnchorTime::addSeconds($trustedNow, $this->configuration->retryDelaySeconds);
        $absenceFields = [
            'external_object_absent' => [
                'externalCallPhase' => 'pre_call',
                'preCallClaimMode' => 'new_attempt',
            ],
            'lookup_unavailable' => [],
        ][$result['outcome']];
        /** @var array<string,mixed> $next */
        $next = array_replace($value, [
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'nextAvailableAt' => $retryAt,
            ...$absenceFields,
        ]);
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
        $this->active(
            $frame,
            $value,
            $trustedNow,
            'reclaim',
            ['external_submission_claim', 'pre_call_retry_claim', 'external_reconciliation_claim'],
        );
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['leaseFencingToken'] = null;
        $next['leaseExpiresAt'] = null;
        $next['nextAvailableAt'] = AuditAnchorTime::addSeconds($trustedNow, $this->configuration->retryDelaySeconds);
        return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow);
    }

    /** @param IntentValue $value @param list<string> $operations */
    private function active(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $trustedNow,
        string $leaseState,
        #[\SensitiveParameter]
        array $operations,
    ): void {
        $originValue = $frame->leaseOrigin()?->toArray();
        $origin = $originValue['operation'] ?? '';
        AuditAnchorTime::assertTransitionTimestamp($trustedNow);
        if (AuditAnchorTime::compare($trustedNow, $value['updatedAt']) < 0) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $trustedNow;
        $comparison = AuditAnchorTime::compare($trustedNow, $leaseExpiresAt);
        $leaseValid =
            [
                'active' => $comparison < 0,
                'reclaim' => $comparison >= 0,
            ][$leaseState] ?? false;
        if (
            [
                [
                    $value['state'],
                    $value['externalCallPhase'],
                    $value['preCallClaimMode'],
                    $value['preCallLookupStatus'],
                    $value['leaseFencingToken'] === null,
                    $value['leaseExpiresAt'] === null,
                    $value['nextAvailableAt'],
                ],
                in_array($origin, $operations, strict: true),
                $leaseValid,
            ] !== [
                ['prepared', 'post_call_uncertain', null, null, false, false, null],
                true,
                true,
            ]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
    }

    /**
     * @param IntentValue $value
     * @param array{anchorObjectVersion:int,acceptedObjectHash:string,acceptedAnchoredAt:string} $accepted
     * @param array{segmentId:string,previousAnchorReceiptHash:?string,checkpointSignedAt:string,anchorReceiptId:string} $binding
     * @return array<string,mixed>
     */
    private static function accepted(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        array $accepted,
        #[\SensitiveParameter]
        array $binding,
    ): array {
        /** @var array<string,mixed> $next */
        $next = $value;
        $next['state'] = 'externally_accepted';
        $next['acceptedObjectVersion'] = $accepted['anchorObjectVersion'];
        $next['acceptedObjectHash'] = $accepted['acceptedObjectHash'];
        $next['acceptedAnchoredAt'] = $accepted['acceptedAnchoredAt'];
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
