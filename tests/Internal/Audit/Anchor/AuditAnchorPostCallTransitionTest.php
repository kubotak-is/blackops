<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorCanonical;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExternalOutcome;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use PHPUnit\Framework\TestCase;

final class AuditAnchorPostCallTransitionTest extends TestCase
{
    public function testAcceptedResultBindsChainAndClearsExternalLease(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $value = $frame->intent()->toArray();
        try {
            $post->result(
                $frame,
                new AuditAnchorExpectation($value['version'], $value['stateHash'], 'wrong-token'),
                AuditAnchorFixture::result('accepted'),
                AuditAnchorFixture::NOW,
            );
            self::fail('stale external token settled a result');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
        $accepted = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
        self::assertSame('externally_accepted', $accepted->intent()->toArray()['state']);
        self::assertNull($accepted->intent()->toArray()['leaseFencingToken']);
        self::assertSame(AuditAnchorFixture::SEGMENT, $accepted->intent()->toArray()['segmentId']);
    }

    public function testRejectedAndUnavailableResultsRejectPartialAcceptedFields(): void
    {
        foreach (['rejected', 'unavailable'] as $status) {
            foreach ([
                'acceptedObjectVersion' => 1,
                'acceptedObjectHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
                'acceptedAnchoredAt' => '2030-01-02T03:05:05.123456Z',
            ] as $field => $partialValue) {
                $value = AuditAnchorFixture::result($status);
                $value[$field] = $partialValue;
                try {
                    AuditAnchorExternalOutcome::result($value);
                    self::fail($status . ' result admitted partial accepted fields: ' . $field);
                } catch (AuditAnchorException $exception) {
                    self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                }
            }
        }
    }

    public function testExpiredPostCallLeaseReclaimsOnlyToReconciliation(): void
    {
        $configuration = new AuditAnchorConfiguration(1, 1);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
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
        $expiredAt = $frame->intent()->toArray()['leaseExpiresAt'];
        $reclaimed = $post->reclaim($frame, AuditAnchorFixture::expectation($frame), $expiredAt);
        self::assertSame('post_call_uncertain', $reclaimed->intent()->toArray()['externalCallPhase']);
        self::assertNull($reclaimed->intent()->toArray()['leaseFencingToken']);
        self::assertSame('2030-01-02T03:04:07.123456Z', $reclaimed->intent()->toArray()['nextAvailableAt']);
    }

    public function testPostCallReclaimSchedulingCoversAllDelayBounds(): void
    {
        $golden = AuditAnchorFixture::golden();
        foreach ([1, 300, 86400] as $delay) {
            $configuration = new AuditAnchorConfiguration(300, $delay);
            $claims = new AuditAnchorClaims($configuration);
            $pre = new AuditAnchorPreCallTransition($configuration);
            $post = new AuditAnchorPostCallTransition($configuration);
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'post-reclaim-token-' . $delay,
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $reclaimed = $post->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            self::assertSame(
                $golden['trace']['delayVectors'][(string) $delay]['post_call_reclaim']['scheduledAt'],
                $reclaimed->intent()->toArray()['nextAvailableAt'],
            );
        }
    }

    public function testOriginalAndReconciliationOwnersCannotSettleEachOther(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
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
            $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('accepted'),
                $frame->intent()->toArray()['updatedAt'],
            );
            self::fail('original external result accepted reconciliation owner');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
    }

    public function testReconciliationAbsentAtThirtyTwoIsTerminal(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = $this->attempt32PostCall($claims, $pre, $post);
        $now = $frame->intent()->toArray()['updatedAt'];
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('unavailable', 32),
            $now,
        );
        $now = $frame->intent()->toArray()['nextAvailableAt'];
        $frame = $claims->externalReconciliation(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reconciliation-token-32',
            $now,
        );
        $frame = $post->reconcile(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            $now,
        );
        self::assertSame('externally_rejected', $frame->intent()->toArray()['state']);
        self::assertSame('attempt_exhausted', $frame->intent()->toArray()['terminalReasonCode']);
        self::assertNull($frame->intent()->toArray()['nextAvailableAt']);
    }

    public function testUnavailableAtThirtyTwoRemainsReconciliationOnlyUntilExplicitAbsence(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = $this->attempt32PostCall($claims, $pre, $post);
        $now = $frame->intent()->toArray()['updatedAt'];
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('unavailable', 32),
            $now,
        );
        self::assertSame('post_call_uncertain', $frame->intent()->toArray()['externalCallPhase']);
        self::assertNotNull($frame->intent()->toArray()['nextAvailableAt']);
        $frame = $claims->externalReconciliation(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reconciliation-token-32',
            $frame->intent()->toArray()['nextAvailableAt'],
        );
        $found = $post->reconcile(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_found'),
            $frame->intent()->toArray()['updatedAt'],
        );
        self::assertSame('externally_accepted', $found->intent()->toArray()['state']);
    }

    public function testRejectedResultIsTerminalAndCannotReopen(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'reject-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $rejected = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('rejected'),
            AuditAnchorFixture::NOW,
        );
        $value = $rejected->intent()->toArray();
        self::assertSame('externally_rejected', $value['state']);
        self::assertSame('permanent_rejection', $value['terminalReasonCode']);
        self::assertNull($value['leaseFencingToken']);
        try {
            $post->result(
                $rejected,
                AuditAnchorFixture::expectation($rejected),
                AuditAnchorFixture::result('accepted'),
                AuditAnchorFixture::NOW,
            );
            self::fail('terminal rejection reopened');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
    }

    public function testUnavailableResultAndReconciliationUnavailableScheduleSameAttempt(): void
    {
        $golden = AuditAnchorFixture::golden();
        foreach ([1, 300, 86400] as $delay) {
            $configuration = new AuditAnchorConfiguration(300, $delay);
            $claims = new AuditAnchorClaims($configuration);
            $pre = new AuditAnchorPreCallTransition($configuration);
            $post = new AuditAnchorPostCallTransition($configuration);
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'unavailable-token-' . $delay,
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
            $frame = $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('unavailable'),
                AuditAnchorFixture::NOW,
            );
            $scheduledAt = $frame->intent()->toArray()['nextAvailableAt'];
            self::assertSame(
                $golden['trace']['delayVectors'][(string) $delay]['post_call_reconciliation_unavailable']['transitions'][3]['scheduledAt'],
                $scheduledAt,
            );
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reconciliation-token-' . $delay,
                $scheduledAt,
            );
            $scheduled = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('lookup_unavailable'),
                $scheduledAt,
            );
            self::assertSame(1, $scheduled->intent()->toArray()['attempt']);
            self::assertSame('post_call_uncertain', $scheduled->intent()->toArray()['externalCallPhase']);
            self::assertSame(
                $golden['trace']['delayVectors'][(string) $delay]['post_call_reconciliation_unavailable']['scheduledAt'],
                $scheduled->intent()->toArray()['nextAvailableAt'],
            );
        }
    }

    public function testPostCallAbsentBelowThirtyTwoSchedulesTheNextExternalAttempt(): void
    {
        $golden = AuditAnchorFixture::golden();
        foreach ([1, 300, 86400] as $delay) {
            $configuration = new AuditAnchorConfiguration(300, $delay);
            $claims = new AuditAnchorClaims($configuration);
            $pre = new AuditAnchorPreCallTransition($configuration);
            $post = new AuditAnchorPostCallTransition($configuration);
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'attempt-one-token-' . $delay,
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $frame = $post->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            $claimAt = $frame->intent()->toArray()['nextAvailableAt'];
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reconciliation-token-' . $delay,
                $claimAt,
            );
            $frame = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $claimAt,
            );
            self::assertSame('pre_call', $frame->intent()->toArray()['externalCallPhase']);
            self::assertSame('new_attempt', $frame->intent()->toArray()['preCallClaimMode']);
            self::assertSame(
                $golden['trace']['delayVectors'][(string) $delay]['post_call_absent_below32']['scheduledAt'],
                $frame->intent()->toArray()['nextAvailableAt'],
            );
            $next = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'attempt-two-token-' . $delay,
                $frame->intent()->toArray()['nextAvailableAt'],
            );
            self::assertSame(2, $next->intent()->toArray()['attempt']);
        }
    }

    public function testActualRetryFamiliesMatchIndependentNodeSuccessorVectors(): void
    {
        $fixture = AuditAnchorFixture::golden();
        foreach ([1, 300, 86400] as $delay) {
            $branches = $fixture['trace']['delayVectors'][(string) $delay];
            foreach ($branches as $name => $branch) {
                $actual = $this->runGoldenBranch($name, $delay);
                self::assertSame($branch['transitions'], $actual);
                self::assertSame($branch['scheduledAt'], $actual[array_key_last($actual)]['scheduledAt']);
            }
        }
    }

    public function testActualResultAttemptOneThirtyOneAndThirtyTwoVectorsMatchNode(): void
    {
        $fixture = AuditAnchorFixture::golden();
        foreach ([1, 31, 32] as $attempt) {
            self::assertSame(
                $fixture['trace']['attemptSequences'][(string) $attempt],
                $this->runGoldenAttempt($attempt),
            );
        }
    }

    public function testExternalResultExtraFieldAndDuplicateSettlementFailClosed(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'shape-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $invalid = AuditAnchorFixture::result('accepted');
        $invalid['nextAvailableAt'] = null;
        try {
            $post->result($frame, AuditAnchorFixture::expectation($frame), $invalid, AuditAnchorFixture::NOW);
            self::fail('extra result field accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::IntegrityInvalid,
                $exception->failure(),
            );
        }
        $accepted = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
        try {
            $post->result(
                $accepted,
                AuditAnchorFixture::expectation($accepted),
                AuditAnchorFixture::result('accepted'),
                AuditAnchorFixture::NOW,
            );
            self::fail('duplicate result settlement accepted');
        } catch (\BlackOps\Internal\Audit\Anchor\AuditAnchorException $exception) {
            self::assertSame(
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFailure::TransitionConflict,
                $exception->failure(),
            );
        }
    }

    private function attempt32PostCall(
        AuditAnchorClaims $claims,
        AuditAnchorPreCallTransition $pre,
        AuditAnchorPostCallTransition $post,
    ): AuditAnchorFrame {
        $frame = AuditAnchorFixture::frame();
        $now = AuditAnchorFixture::NOW;
        for ($attempt = 1; $attempt <= 31; ++$attempt) {
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'external-token-' . $attempt,
                $now,
            );
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $now,
            );
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), $now);
            $frame = $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('unavailable', $attempt),
                $now,
            );
            $now = $frame->intent()->toArray()['nextAvailableAt'];
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reconciliation-token-' . $attempt,
                $now,
            );
            $frame = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $now,
            );
            $now = $frame->intent()->toArray()['nextAvailableAt'];
        }
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-32',
            $now,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            $now,
        );
        return $pre->callStart($frame, AuditAnchorFixture::expectation($frame), $now);
    }

    /** @return list<array<string,mixed>> */
    private function runGoldenBranch(string $name, int $delay): array
    {
        $configuration = new AuditAnchorConfiguration(300, $delay);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $actual = [];
        $advance = function (string $operation, AuditAnchorFrame $before, AuditAnchorFrame $after) use (
            &$actual,
        ): AuditAnchorFrame {
            $value = $after->intent()->toArray();
            $actual[] = [
                'operation' => $operation,
                'fromVersion' => $value['version'] - 1,
                'toVersion' => $value['version'],
                'previousStateHash' => $before->intent()->toArray()['stateHash'],
                'updatedAt' => $value['updatedAt'],
                'scheduledAt' => $value['nextAvailableAt'],
                'successorStateHash' => $value['stateHash'],
                'successorBytes' => AuditAnchorCanonical::jcs($value),
            ];
            return $after;
        };
        $claim = function (string $token) use (&$frame, $claims, $advance): void {
            $before = $frame;
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                $token,
                AuditAnchorFixture::NOW,
            );
            $frame = $advance('external_submission_claim', $before, $frame);
        };
        $lookupAbsent = function () use (&$frame, $pre, $advance): void {
            $before = $frame;
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            $frame = $advance('lookup_absent', $before, $frame);
        };
        $callStart = function () use (&$frame, $pre, $advance): void {
            $before = $frame;
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
            $frame = $advance('call_start', $before, $frame);
        };
        $claim(
            [
                'pre_call_lookup_unavailable' => 'external-token-lookup-',
                'pre_call_pending_reclaim' => 'pending-token-',
                'pre_call_absent_reclaim' => 'absent-token-',
                'post_call_result_unavailable' => 'result-token-',
                'post_call_reclaim' => 'post-reclaim-token-',
                'post_call_reconciliation_unavailable' => 'reconciliation-base-token-',
                'post_call_absent_below32' => 'post-absent-token-',
            ][$name] . $delay,
        );
        if ($name === 'pre_call_lookup_unavailable') {
            $before = $frame;
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('lookup_unavailable'),
                AuditAnchorFixture::NOW,
            );
            $advance('lookup_unavailable', $before, $frame);
            return $actual;
        }
        if ($name === 'pre_call_pending_reclaim') {
            $before = $frame;
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $frame = $pre->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            $advance('pre_call_reclaim', $before, $frame);
            return $actual;
        }
        if ($name === 'pre_call_absent_reclaim') {
            $lookupAbsent();
            $before = $frame;
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $frame = $pre->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            $advance('pre_call_reclaim', $before, $frame);
            return $actual;
        }
        $lookupAbsent();
        $callStart();
        if ($name === 'post_call_result_unavailable') {
            $before = $frame;
            $frame = $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('unavailable'),
                AuditAnchorFixture::NOW,
            );
            $advance('result_unavailable', $before, $frame);
            return $actual;
        }
        if ($name === 'post_call_reclaim') {
            $before = $frame;
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $frame = $post->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            $advance('post_call_reclaim', $before, $frame);
            return $actual;
        }
        if ($name === 'post_call_reconciliation_unavailable') {
            $before = $frame;
            $frame = $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('unavailable'),
                AuditAnchorFixture::NOW,
            );
            $frame = $advance('result_unavailable', $before, $frame);
            $claimAt = $frame->intent()->toArray()['nextAvailableAt'];
            $before = $frame;
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reconciliation-token-' . $delay,
                $claimAt,
            );
            $frame = $advance('external_reconciliation_claim', $before, $frame);
            $before = $frame;
            $frame = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('lookup_unavailable'),
                $claimAt,
            );
            $advance('reconciliation_unavailable', $before, $frame);
            return $actual;
        }
        if ($name === 'post_call_absent_below32') {
            $before = $frame;
            $expired = $frame->intent()->toArray()['leaseExpiresAt'];
            $frame = $post->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
            $frame = $advance('post_call_reclaim', $before, $frame);
            $claimAt = $frame->intent()->toArray()['nextAvailableAt'];
            $before = $frame;
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'post-reconciliation-token-' . $delay,
                $claimAt,
            );
            $frame = $advance('external_reconciliation_claim', $before, $frame);
            $before = $frame;
            $frame = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $claimAt,
            );
            $advance('reconciliation_absent', $before, $frame);
            return $actual;
        }
        self::fail('unknown golden retry branch: ' . $name);
    }

    /** @return list<array<string,mixed>> */
    private function runGoldenAttempt(int $targetAttempt): array
    {
        $configuration = new AuditAnchorConfiguration(300, 300);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $now = AuditAnchorFixture::NOW;
        $actual = [];
        $advance = function (string $operation, AuditAnchorFrame $before, AuditAnchorFrame $after) use (
            &$actual,
        ): void {
            $value = $after->intent()->toArray();
            $actual[] = [
                'operation' => $operation,
                'fromVersion' => $value['version'] - 1,
                'toVersion' => $value['version'],
                'previousStateHash' => $before->intent()->toArray()['stateHash'],
                'updatedAt' => $value['updatedAt'],
                'scheduledAt' => $value['nextAvailableAt'],
                'successorStateHash' => $value['stateHash'],
                'successorBytes' => AuditAnchorCanonical::jcs($value),
            ];
        };
        for ($attempt = 1; $attempt <= $targetAttempt; ++$attempt) {
            $before = $frame;
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'external-token-' . $attempt,
                $now,
            );
            $advance('external_submission_claim', $before, $frame);
            $before = $frame;
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $now,
            );
            $advance('lookup_absent', $before, $frame);
            $before = $frame;
            $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), $now);
            $advance('call_start', $before, $frame);
            $before = $frame;
            $frame = $post->result(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::result('unavailable', $attempt),
                $now,
            );
            $advance('result_unavailable', $before, $frame);
            if ($attempt === $targetAttempt) {
                break;
            }
            $now = $frame->intent()->toArray()['nextAvailableAt'];
            $before = $frame;
            $frame = $claims->externalReconciliation(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'reconciliation-token-' . $attempt,
                $now,
            );
            $advance('external_reconciliation_claim', $before, $frame);
            $before = $frame;
            $frame = $post->reconcile(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                $now,
            );
            $advance('reconciliation_absent', $before, $frame);
            $now = $frame->intent()->toArray()['nextAvailableAt'];
        }
        return $actual;
    }
}
