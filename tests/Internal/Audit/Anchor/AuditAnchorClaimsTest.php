<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use PHPUnit\Framework\TestCase;

final class AuditAnchorClaimsTest extends TestCase
{
    public function testExternalSubmissionClaimInstallsPurposeAndAdvancesAttempt(): void
    {
        $frame = AuditAnchorFixture::frame();
        $next = new AuditAnchorClaims(new AuditAnchorConfiguration())->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        self::assertSame(1, $next->intent()->toArray()['attempt']);
        self::assertSame('external_submission_claim', $next->leaseOrigin()?->toArray()['operation']);
        self::assertSame('pending', $next->intent()->toArray()['preCallLookupStatus']);
    }

    public function testRetryAndReconciliationClaimsRequireTheirDistinctDueModes(): void
    {
        $frame = AuditAnchorFixture::frame();
        $claims = new AuditAnchorClaims(new AuditAnchorConfiguration());
        $claimed = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        try {
            $claims->preCallRetry(
                $claimed,
                AuditAnchorFixture::expectation($claimed),
                'retry-token',
                AuditAnchorFixture::LATER,
            );
            self::fail('retry claim bypassed pending lookup');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
    }

    public function testPreCallRetryAndExternalReconciliationInstallDistinctOrigins(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new \BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition($configuration);
        $post = new \BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('lookup_unavailable'),
            AuditAnchorFixture::NOW,
        );
        $frame = $claims->preCallRetry(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'retry-token',
            $frame->intent()->toArray()['nextAvailableAt'],
        );
        self::assertSame('pre_call_retry_claim', $frame->leaseOrigin()?->toArray()['operation']);

        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            $frame->intent()->toArray()['updatedAt'],
        );
        $frame = $pre->callStart(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $frame->intent()->toArray()['updatedAt'],
        );
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('unavailable'),
            $frame->intent()->toArray()['updatedAt'],
        );
        $frame = $claims->externalReconciliation(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reconciliation-token',
            $frame->intent()->toArray()['nextAvailableAt'],
        );
        self::assertSame('external_reconciliation_claim', $frame->leaseOrigin()?->toArray()['operation']);
    }

    public function testMalformedTrustedTransitionTimeMapsToTransitionConflict(): void
    {
        $frame = AuditAnchorFixture::frame();
        try {
            new AuditAnchorClaims(new AuditAnchorConfiguration())->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'external-token',
                'malformed-time',
            );
            self::fail('malformed transition time accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
    }

    public function testExpectedVersionHashAndTokenSubstitutionsConflictBeforeAClaim(): void
    {
        $frame = AuditAnchorFixture::frame();
        $claims = new AuditAnchorClaims(new AuditAnchorConfiguration());
        foreach ([
            new AuditAnchorExpectation(2, str_repeat('a', 64), null),
            new AuditAnchorExpectation(1, str_repeat('b', 64), null),
            new AuditAnchorExpectation(
                1,
                AuditAnchorFixture::frame()->intent()->toArray()['stateHash'],
                'unexpected-token',
            ),
        ] as $expected) {
            try {
                $claims->externalSubmission($frame, $expected, 'claim-token', AuditAnchorFixture::NOW);
                self::fail('substituted expectation accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testClockRollbackAndLeaseOverflowLeaveGenesisUnchanged(): void
    {
        $frame = AuditAnchorFixture::frame();
        $before = $frame->toArray();
        $claims = new AuditAnchorClaims(new AuditAnchorConfiguration(1, 1));
        try {
            $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'rollback-token',
                '2030-01-02T03:04:05.123455Z',
            );
            self::fail('clock rollback accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
        self::assertSame($before, $frame->toArray());

        try {
            $claims->externalSubmission(
                $frame,
                new AuditAnchorExpectation(2, str_repeat('a', 64), null),
                'precedence-token',
                '9999-12-31T23:59:59.999999Z',
            );
            self::fail('capacity was checked before the stale expectation');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $frame->toArray());

        try {
            $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'overflow-token',
                '9999-12-31T23:59:59.999999Z',
            );
            self::fail('lease timestamp overflow accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::ResourceUnavailable, $exception->failure());
        }
        self::assertSame($before, $frame->toArray());

        $nearBoundary = AuditAnchorFixture::frame(null, '9999-12-31T23:59:58.000000Z');
        $nearBoundaryBefore = $nearBoundary->toArray();
        try {
            $claims->externalSubmission(
                $nearBoundary,
                AuditAnchorFixture::expectation($nearBoundary),
                'rollback-before-overflow-token',
                '9999-12-31T23:59:57.000000Z',
            );
            self::fail('rollback was checked after lease capacity');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($nearBoundaryBefore, $nearBoundary->toArray());
    }

    public function testSettlementOwnersRemainDisjointAcrossExternalAndReconciliationClaims(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new \BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition($configuration);
        $post = new \BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        try {
            $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_found'),
                AuditAnchorFixture::NOW,
            );
            self::fail('reconciliation settled an original external claim');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('unavailable'),
            AuditAnchorFixture::NOW,
        );
        $frame = $claims->externalReconciliation(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reconciliation-token',
            $frame->intent()->toArray()['nextAvailableAt'],
        );
        try {
            $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'wrong-purpose-token',
                $frame->intent()->toArray()['updatedAt'],
            );
            self::fail('external submission claimed reconciliation state');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
    }
}
