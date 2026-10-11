<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use PHPUnit\Framework\TestCase;

final class AuditAnchorPreCallTransitionTest extends TestCase
{
    public function testPendingAbsentAndCallStartPreserveAttemptAndMarkPostCallBeforeEffect(): void
    {
        $claims = new AuditAnchorClaims(new AuditAnchorConfiguration());
        $transition = new AuditAnchorPreCallTransition(new AuditAnchorConfiguration());
        $frame = AuditAnchorFixture::frame();
        $claimed = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        $absent = $transition->lookup(
            $claimed,
            AuditAnchorFixture::expectation($claimed),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        self::assertSame('absent', $absent->intent()->toArray()['preCallLookupStatus']);
        $started = $transition->callStart($absent, AuditAnchorFixture::expectation($absent), AuditAnchorFixture::NOW);
        self::assertSame('post_call_uncertain', $started->intent()->toArray()['externalCallPhase']);
        self::assertNull($started->intent()->toArray()['preCallClaimMode']);
    }

    public function testUnavailableSchedulesSameAttemptWithMicrosecondsPreserved(): void
    {
        $claims = new AuditAnchorClaims(new AuditAnchorConfiguration());
        $transition = new AuditAnchorPreCallTransition(new AuditAnchorConfiguration());
        $frame = AuditAnchorFixture::frame();
        $claimed = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        $next = $transition->lookup(
            $claimed,
            AuditAnchorFixture::expectation($claimed),
            [
                'schemaVersion' => 1,
                'kind' => 'audit_anchor_reconciliation',
                'outcome' => 'lookup_unavailable',
                'foundObject' => null,
                'reasonCode' => 'lookup_unavailable',
            ],
            AuditAnchorFixture::NOW,
        );
        self::assertSame('2030-01-02T03:09:05.123456Z', $next->intent()->toArray()['nextAvailableAt']);
        self::assertSame(1, $next->intent()->toArray()['attempt']);
    }

    public function testExpiredPendingAndAbsentClaimsReclaimIntoSameAttemptRetry(): void
    {
        $configuration = new AuditAnchorConfiguration(1, 1);
        $claims = new AuditAnchorClaims($configuration);
        $transition = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'pending-token',
            AuditAnchorFixture::NOW,
        );
        $expiredAt = $frame->intent()->toArray()['leaseExpiresAt'];
        $reclaimed = $transition->reclaim($frame, AuditAnchorFixture::expectation($frame), $expiredAt);
        self::assertSame('retry_same_attempt', $reclaimed->intent()->toArray()['preCallClaimMode']);
        self::assertSame('2030-01-02T03:04:07.123456Z', $reclaimed->intent()->toArray()['nextAvailableAt']);

        $claimed = $claims->preCallRetry(
            $reclaimed,
            AuditAnchorFixture::expectation($reclaimed),
            'absent-token',
            $reclaimed->intent()->toArray()['nextAvailableAt'],
        );
        $absent = $transition->lookup(
            $claimed,
            AuditAnchorFixture::expectation($claimed),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            $claimed->intent()->toArray()['updatedAt'],
        );
        $expiredAt = $absent->intent()->toArray()['leaseExpiresAt'];
        $reclaimedAbsent = $transition->reclaim($absent, AuditAnchorFixture::expectation($absent), $expiredAt);
        self::assertSame(1, $reclaimedAbsent->intent()->toArray()['attempt']);
        self::assertNull($reclaimedAbsent->intent()->toArray()['preCallLookupStatus']);
        self::assertSame('retry_same_attempt', $reclaimedAbsent->intent()->toArray()['preCallClaimMode']);
    }

    public function testFoundLookupBindsTheWholeExternalChainAndRejectsSubstitution(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $transition = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $found = $transition->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_found'),
            AuditAnchorFixture::NOW,
        );
        self::assertSame('externally_accepted', $found->intent()->toArray()['state']);
        self::assertSame(AuditAnchorFixture::SEGMENT, $found->intent()->toArray()['segmentId']);
        $value = $frame->intent()->toArray();
        foreach ([
            ['anchorObjectRef' => 'other-object'],
            ['anchorObjectVersion' => 2],
            ['acceptedObjectHash' => str_repeat('d', 64)],
            ['acceptedAnchoredAt' => '2030-01-02T03:02:05.123456Z'],
            ['segmentId' => '01941f29-7c00-7000-8000-000000000103'],
            ['previousAnchorReceiptHash' => AuditAnchorFixture::PREVIOUS_RECEIPT_HASH],
        ] as $foundMutation) {
            $bad = AuditAnchorFixture::reconciliation('external_object_found');
            $bad['foundObject'] = [...$bad['foundObject'], ...$foundMutation];
            try {
                $transition->lookup($frame, AuditAnchorFixture::expectation($frame), $bad, AuditAnchorFixture::NOW);
                self::fail('substituted found object accepted');
            } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
                self::assertSame(
                    \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::IntegrityInvalid,
                    $exception->failure(),
                );
            }
        }
        $unknownFoundField = AuditAnchorFixture::reconciliation('external_object_found');
        $unknownFoundField['foundObject']['unexpected'] = true;
        try {
            $transition->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                $unknownFoundField,
                AuditAnchorFixture::NOW,
            );
            self::fail('unknown found-object field accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::IntegrityInvalid,
                $exception->failure(),
            );
        }
        self::assertSame('prepared', $value['state']);
    }

    public function testFoundLookupCarriesANonGenesisPredecessorIntoTheAcceptedChain(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $transition = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame(AuditAnchorFixture::PREVIOUS_RECEIPT_HASH);
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'predecessor-token',
            AuditAnchorFixture::NOW,
        );
        $found = AuditAnchorFixture::reconciliation('external_object_found');
        $found['foundObject']['anchorObjectVersion'] = 5;
        $found['foundObject']['previousAnchorReceiptHash'] = AuditAnchorFixture::PREVIOUS_RECEIPT_HASH;
        $accepted = $transition->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $found,
            AuditAnchorFixture::NOW,
        );
        $value = $accepted->intent()->toArray();
        self::assertSame(5, $value['acceptedObjectVersion']);
        self::assertSame(AuditAnchorFixture::PREVIOUS_RECEIPT_HASH, $value['previousAnchorReceiptHash']);
    }

    public function testRetrySchedulingCoversAllDelayBoundsAndDueEquality(): void
    {
        foreach ([
            [1,     '2030-01-02T03:04:06.123456Z'],
            [300,   '2030-01-02T03:09:05.123456Z'],
            [86400, '2030-01-03T03:04:05.123456Z'],
        ] as [$delay, $scheduled]) {
            $configuration = new AuditAnchorConfiguration(300, $delay);
            $claims = new AuditAnchorClaims($configuration);
            $transition = new AuditAnchorPreCallTransition($configuration);
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'delay-token-' . $delay,
                AuditAnchorFixture::NOW,
            );
            $scheduledFrame = $transition->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('lookup_unavailable'),
                AuditAnchorFixture::NOW,
            );
            self::assertSame($scheduled, $scheduledFrame->intent()->toArray()['nextAvailableAt']);
            $retried = $claims->preCallRetry(
                $scheduledFrame,
                AuditAnchorFixture::expectation($scheduledFrame),
                'retry-token-' . $delay,
                $scheduled,
            );
            self::assertSame(1, $retried->intent()->toArray()['attempt']);
        }
    }

    public function testPreCallReclaimSchedulingCoversAllDelayBounds(): void
    {
        $golden = AuditAnchorFixture::golden();
        foreach ([1, 300, 86400] as $delay) {
            $configuration = new AuditAnchorConfiguration(300, $delay);
            $claims = new AuditAnchorClaims($configuration);
            $transition = new AuditAnchorPreCallTransition($configuration);
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reclaim-token-' . $delay,
                AuditAnchorFixture::NOW,
            );
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $reclaimed = $transition->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            self::assertSame(
                $golden['trace']['delayVectors'][(string) $delay]['pre_call_pending_reclaim']['scheduledAt'],
                $reclaimed->intent()->toArray()['nextAvailableAt'],
            );
        }
    }

    public function testCallStartBeforeAbsentLookupAndAtFutureDueTimeFailClosed(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $transition = new AuditAnchorPreCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $pending = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'pending-token',
            AuditAnchorFixture::NOW,
        );
        try {
            $transition->callStart($pending, AuditAnchorFixture::expectation($pending), AuditAnchorFixture::NOW);
            self::fail('call started before explicit absent lookup');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }

        $unavailable = $transition->lookup(
            $pending,
            AuditAnchorFixture::expectation($pending),
            AuditAnchorFixture::reconciliation('lookup_unavailable'),
            AuditAnchorFixture::NOW,
        );
        try {
            $claims->preCallRetry(
                $unavailable,
                AuditAnchorFixture::expectation($unavailable),
                'future-token',
                '2030-01-02T03:09:04.123456Z',
            );
            self::fail('future retry was claimed');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
    }

    public function testRepeatedLookupCallStartAndUnknownAdapterFieldsFailClosed(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $transition = new AuditAnchorPreCallTransition($configuration);
        $frame = $claims->externalSubmission(
            AuditAnchorFixture::frame(),
            AuditAnchorFixture::expectation(AuditAnchorFixture::frame()),
            'repeat-token',
            AuditAnchorFixture::NOW,
        );
        $absent = $transition->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        try {
            $transition->lookup(
                $absent,
                AuditAnchorFixture::expectation($absent),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            self::fail('repeated lookup accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
        $started = $transition->callStart($absent, AuditAnchorFixture::expectation($absent), AuditAnchorFixture::NOW);
        try {
            $transition->callStart($started, AuditAnchorFixture::expectation($started), AuditAnchorFixture::NOW);
            self::fail('repeated call start accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
        $unknown = AuditAnchorFixture::reconciliation('external_object_absent');
        $unknown['unexpected'] = true;
        try {
            $transition->lookup(
                $frame,
                new \BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation(2, str_repeat('a', 64), null),
                $unknown,
                AuditAnchorFixture::NOW,
            );
            self::fail('unknown reconciliation field accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::IntegrityInvalid,
                $exception->failure(),
            );
        }
    }
}
