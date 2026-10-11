<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorBinding;
use BlackOps\Internal\Audit\Anchor\AuditAnchorCanonical;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use PHPUnit\Framework\TestCase;

final class AuditAnchorFrameTest extends TestCase
{
    public function testFrameRestoresWholeAssociationAndKeepsExpiredLeaseAdmissible(): void
    {
        $frame = AuditAnchorFixture::frame();
        self::assertNull($frame->leaseOrigin());
        $restored = AuditAnchorFrame::restore($frame->toArray());
        self::assertSame($frame->toArray(), $restored->toArray());
    }

    public function testCreationMapsMalformedTransitionTimeToConflictAfterInputAdmission(): void
    {
        try {
            AuditAnchorFrame::create(
                [
                    'intentId' => 'intent-precedence',
                    'partitionId' => 'system:global',
                    'checkpointId' => 'checkpoint-precedence',
                    'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
                    'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
                    'anchorStoreRef' => 'anchor-store',
                    'anchorObjectRef' => 'object-precedence',
                    'expectedPredecessorVersion' => null,
                    'expectedPredecessorHash' => null,
                    'conditionalCreateToken' => 'conditional-precedence',
                ],
                AuditAnchorBinding::fromArray([
                    'segmentId' => AuditAnchorFixture::SEGMENT,
                    'previousAnchorReceiptHash' => null,
                    'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
                    'anchorReceiptId' => 'receipt-precedence',
                ]),
                'raw-precedence',
                new AuditAnchorExpectation(0, null, null),
                'malformed-time',
            );
            self::fail('malformed creation timestamp accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCreationAdmitsEveryScalarAndBindingBeforeExpectationOrTime(): void
    {
        $request = [
            'intentId' => 'intent-admission',
            'partitionId' => 'system:global',
            'checkpointId' => 'checkpoint-admission',
            'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
            'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
            'anchorStoreRef' => 'anchor-store',
            'anchorObjectRef' => 'object-admission',
            'expectedPredecessorVersion' => null,
            'expectedPredecessorHash' => null,
            'conditionalCreateToken' => 'conditional-admission',
        ];
        $binding = AuditAnchorBinding::fromArray([
            'segmentId' => AuditAnchorFixture::SEGMENT,
            'previousAnchorReceiptHash' => null,
            'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
            'anchorReceiptId' => 'receipt-admission',
        ]);
        foreach ([
            ['checkpointId' => false],
            ['checkpointHash' => ['nested']],
            ['anchorObjectRef' => ['nested']],
            ['expectedPredecessorVersion' => '1'],
            ['expectedPredecessorHash' => AuditAnchorFixture::CHECKPOINT_HASH],
        ] as $mutation) {
            try {
                AuditAnchorFrame::create(
                    [...$request, ...$mutation],
                    $binding,
                    'raw-admission',
                    new AuditAnchorExpectation(1, str_repeat('a', 64), null),
                    'malformed-time',
                );
                self::fail('malformed creation field accepted before precedence checks');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
        foreach ([
            'extra' => [...$request, 'unexpected' => true],
            'missing' => array_diff_key($request, ['intentId' => true]),
        ] as $invalidRequest) {
            try {
                AuditAnchorFrame::create(
                    $invalidRequest,
                    $binding,
                    'raw-admission',
                    new AuditAnchorExpectation(0, null, null),
                    AuditAnchorFixture::NOW,
                );
                self::fail('creation closed set accepted ' . array_key_first($invalidRequest));
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            }
        }
    }

    public function testCreationRejectsEitherPredecessorNullPairBeforeExpectationTimeOrHash(): void
    {
        $request = [
            'intentId' => 'intent-pair-order',
            'partitionId' => 'system:global',
            'checkpointId' => 'checkpoint-pair-order',
            'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
            'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
            'anchorStoreRef' => 'anchor-store',
            'anchorObjectRef' => 'object-pair-order',
            'expectedPredecessorVersion' => null,
            'expectedPredecessorHash' => null,
            'conditionalCreateToken' => 'conditional-pair-order',
        ];
        $binding = AuditAnchorBinding::fromArray([
            'segmentId' => AuditAnchorFixture::SEGMENT,
            'previousAnchorReceiptHash' => null,
            'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
            'anchorReceiptId' => 'receipt-pair-order',
        ]);
        $beforeRequest = $request;
        $beforeBinding = $binding->toArray();
        foreach ([
            [...$request, 'expectedPredecessorVersion' => 1],
            [...$request, 'expectedPredecessorHash' => AuditAnchorFixture::CHECKPOINT_HASH],
        ] as $invalidRequest) {
            $invalidBinding = $binding;
            if ($invalidRequest['expectedPredecessorHash'] !== null) {
                $invalidBinding = AuditAnchorBinding::fromArray([
                    ...$beforeBinding,
                    'previousAnchorReceiptHash' => AuditAnchorFixture::PREVIOUS_RECEIPT_HASH,
                ]);
            }
            try {
                AuditAnchorFrame::create(
                    $invalidRequest,
                    $invalidBinding,
                    'raw-pair-order',
                    new AuditAnchorExpectation(1, str_repeat('a', 64), null),
                    'malformed-time',
                );
                self::fail('incomplete predecessor pair accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame($beforeRequest, $request);
            self::assertSame($beforeBinding, $binding->toArray());
        }

        $genesis = AuditAnchorFrame::create(
            $request,
            $binding,
            'raw-pair-order',
            new AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        self::assertSame('prepared', $genesis->intent()->state()->value);
        $linked = [
            ...$request,
            'expectedPredecessorVersion' => 4,
            'expectedPredecessorHash' => AuditAnchorFixture::CHECKPOINT_HASH,
        ];
        $linkedBinding = AuditAnchorBinding::fromArray([
            ...$beforeBinding,
            'previousAnchorReceiptHash' => AuditAnchorFixture::PREVIOUS_RECEIPT_HASH,
        ]);
        $linkedFrame = AuditAnchorFrame::create(
            $linked,
            $linkedBinding,
            'raw-pair-order-linked',
            new AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        self::assertSame(4, $linkedFrame->intent()->toArray()['expectedPredecessorVersion']);
    }

    public function testPreparedCarrierBindingIsSuppliedAndAcceptedBindingSubstitutionFailsClosed(): void
    {
        $value = AuditAnchorFixture::frame()->toArray();
        $value['binding']['anchorReceiptId'] = 'other-receipt';
        self::assertSame('other-receipt', AuditAnchorFrame::restore($value)->binding()->toArray()['anchorReceiptId']);

        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $preCall = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $pending = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'accepted-binding-token',
            AuditAnchorFixture::NOW,
        );
        $accepted = $preCall
            ->lookup(
                $pending,
                AuditAnchorFixture::expectation($pending),
                AuditAnchorFixture::reconciliation('external_object_found'),
                AuditAnchorFixture::NOW,
            )
            ->toArray();

        foreach ([
            ['anchorReceiptId' => 'other-receipt'],
            ['segmentId' => '01941f29-7c00-7000-8000-000000000103'],
            ['previousAnchorReceiptHash' => str_repeat('d', 64)],
        ] as $mutation) {
            $mutated = $accepted;
            $mutated['binding'] = [...$mutated['binding'], ...$mutation];
            $this->assertIntegrity($mutated);
        }

        $value = AuditAnchorFixture::frame()->toArray();
        $value['leaseOrigin'] = [
            'operation' => 'external_submission_claim',
            'intentId' => 'intent-001',
            'attempt' => 1,
            'leaseFencingToken' => 'token',
            'claimVersion' => 2,
            'claimStateHash' => str_repeat('a', 64),
        ];
        $this->assertIntegrity($value);
    }

    public function testRestoreDetachesEmbeddedReferencesAndReturnedProjections(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $frame = AuditAnchorFixture::frame();
        $claimed = new AuditAnchorClaims($configuration)->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reference-token',
            AuditAnchorFixture::NOW,
        );
        $carrier = $claimed->toArray();
        $intentId = $carrier['intent']['intentId'];
        $anchorReceiptId = $carrier['binding']['anchorReceiptId'];
        $leaseFencingToken = $carrier['leaseOrigin']['leaseFencingToken'];
        $carrier['intent']['intentId'] = &$intentId;
        $carrier['binding']['anchorReceiptId'] = &$anchorReceiptId;
        $carrier['leaseOrigin']['leaseFencingToken'] = &$leaseFencingToken;

        $restored = AuditAnchorFrame::restore($carrier);
        $before = $restored->toArray();
        $beforeHash = $before['intent']['stateHash'];
        $intentId = 'caller-mutated-intent';
        $anchorReceiptId = 'caller-mutated-receipt';
        $leaseFencingToken = 'caller-mutated-token';
        self::assertSame('caller-mutated-intent', $carrier['intent']['intentId']);
        self::assertSame('caller-mutated-receipt', $carrier['binding']['anchorReceiptId']);
        self::assertSame('caller-mutated-token', $carrier['leaseOrigin']['leaseFencingToken']);
        self::assertSame($before, $restored->toArray());

        $projection = $restored->toArray();
        $projectedIntentId = &$projection['intent']['intentId'];
        $projectedIntentId = 'projection-mutated-intent';
        self::assertSame($before, $restored->toArray());
        self::assertSame($beforeHash, $restored->toArray()['intent']['stateHash']);
        self::assertSame($before['intent']['intentId'], $restored->intent()->toArray()['intentId']);
    }

    public function testExternalPredecessorObjectHashRemainsDistinctFromReceiptBindingHash(): void
    {
        $frame = AuditAnchorFrame::create(
            [
                'intentId' => 'intent-distinct',
                'partitionId' => 'system:global',
                'checkpointId' => 'checkpoint-distinct',
                'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
                'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
                'anchorStoreRef' => 'anchor-store',
                'anchorObjectRef' => 'object-distinct',
                'expectedPredecessorVersion' => 4,
                'expectedPredecessorHash' => AuditAnchorFixture::CHECKPOINT_HASH,
                'conditionalCreateToken' => 'conditional-distinct',
            ],
            AuditAnchorBinding::fromArray([
                'segmentId' => AuditAnchorFixture::SEGMENT,
                'previousAnchorReceiptHash' => AuditAnchorFixture::PREVIOUS_RECEIPT_HASH,
                'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
                'anchorReceiptId' => 'receipt-distinct',
            ]),
            'raw-distinct',
            new AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        self::assertSame($frame->toArray(), AuditAnchorFrame::restore($frame->toArray())->toArray());
    }

    public function testCarrierFieldSetsAreClosedAndProjectionMutationIsDetached(): void
    {
        $frame = AuditAnchorFixture::frame();
        $value = $frame->toArray();
        $value['binding']['unexpected'] = true;
        $this->assertIntegrity($value);

        $value = $frame->toArray();
        $value['unexpected'] = true;
        $this->assertIntegrity($value);

        $value = $frame->toArray();
        unset($value['binding']);
        $this->assertIntegrity($value);

        $value = $frame->toArray();
        unset($value['intent']['stateHash']);
        $this->assertIntegrity($value);

        $binding = $frame->binding()->toArray();
        $binding['anchorReceiptId'] = 'caller-mutated';
        self::assertSame('receipt-001', $frame->binding()->toArray()['anchorReceiptId']);
    }

    public function testRestoreEnforcesClaimPurposeAndExactPendingAbsentPostCallDeltas(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $preCall = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();

        $pending = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $pendingValue = $pending->toArray();
        $pendingValue['leaseOrigin']['claimVersion'] = 1;
        $this->assertIntegrity($pendingValue);

        foreach ([
            ['intentId' => 'other-intent'],
            ['attempt' => 2],
            ['leaseFencingToken' => 'other-token'],
        ] as $originMutation) {
            $pendingValue = $pending->toArray();
            $pendingValue['leaseOrigin'] = [...$pendingValue['leaseOrigin'], ...$originMutation];
            $this->assertIntegrity($pendingValue);
        }

        $pendingValue = $pending->toArray();
        $pendingValue['leaseOrigin']['claimStateHash'] = str_repeat('d', 64);
        $this->assertIntegrity($pendingValue);

        $pendingValue = $pending->toArray();
        $pendingValue['leaseOrigin']['operation'] = 'pre_call_retry_claim';
        $this->assertIntegrity($pendingValue);

        $pendingValue = $pending->toArray();
        $pendingValue['intent']['externalCallPhase'] = 'post_call_uncertain';
        $pendingValue['intent']['preCallClaimMode'] = null;
        $pendingValue['intent']['preCallLookupStatus'] = null;
        $pendingValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($pendingValue['intent']);
        $this->assertIntegrity($pendingValue);

        $pendingValue = $pending->toArray();
        $pendingValue['leaseOrigin']['unexpected'] = true;
        $this->assertIntegrity($pendingValue);

        $pendingValue = $pending->toArray();
        unset($pendingValue['leaseOrigin']['claimStateHash']);
        $this->assertIntegrity($pendingValue);

        $absent = $preCall->lookup(
            $pending,
            AuditAnchorFixture::expectation($pending),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $absentValue = $absent->toArray();
        $absentValue['leaseOrigin']['claimVersion'] = $absentValue['intent']['version'];
        $this->assertIntegrity($absentValue);

        $absentValue = $absent->toArray();
        $absentValue['leaseOrigin']['claimStateHash'] = str_repeat('e', 64);
        $this->assertIntegrity($absentValue);

        $absentValue = $absent->toArray();
        $absentValue['intent']['preCallClaimMode'] = 'retry_same_attempt';
        $absentValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($absentValue['intent']);
        $this->assertIntegrity($absentValue);

        $absentValue = $absent->toArray();
        $absentValue['intent']['preCallLookupStatus'] = 'pending';
        $absentValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($absentValue['intent']);
        $this->assertIntegrity($absentValue);

        $retryClaimed = $claims->preCallRetry(
            $reclaimed = $preCall->reclaim(
                $absent,
                AuditAnchorFixture::expectation($absent),
                $absent->intent()->toArray()['leaseExpiresAt'],
            ),
            AuditAnchorFixture::expectation($reclaimed),
            'retry-origin-token',
            $reclaimed->intent()->toArray()['nextAvailableAt'],
        );
        $retryAbsent = $preCall->lookup(
            $retryClaimed,
            AuditAnchorFixture::expectation($retryClaimed),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            $retryClaimed->intent()->toArray()['updatedAt'],
        );
        $retryAbsentValue = $retryAbsent->toArray();
        $retryAbsentValue['intent']['preCallClaimMode'] = 'new_attempt';
        $retryAbsentValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($retryAbsentValue['intent']);
        $this->assertIntegrity($retryAbsentValue);

        $postCall = $preCall->callStart($absent, AuditAnchorFixture::expectation($absent), AuditAnchorFixture::NOW);
        $postCallValue = $postCall->toArray();
        $postCallValue['leaseOrigin']['claimVersion'] = $postCallValue['intent']['version'] - 1;
        $this->assertIntegrity($postCallValue);

        $postCallValue = $postCall->toArray();
        $postCallValue['intent']['preCallClaimMode'] = 'new_attempt';
        $postCallValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($postCallValue['intent']);
        $this->assertIntegrity($postCallValue);
    }

    /** @param array<string,mixed> $value */
    private function assertIntegrity(array $value): void
    {
        try {
            AuditAnchorFrame::restore($value);
            self::fail('invalid frame accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }
}
