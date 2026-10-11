<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final class AuditAnchorClaims
{
    public function __construct(
        private readonly AuditAnchorConfiguration $configuration,
    ) {}

    public function externalSubmission(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'external_submission_claim');
    }

    public function preCallRetry(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'pre_call_retry_claim');
    }

    public function externalReconciliation(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditAnchorFrame {
        return $this->claim($frame, $expected, $newToken, $trustedNow, 'external_reconciliation_claim');
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
            $required = [
                'external_submission_claim' => ['prepared', 'pre_call', 'new_attempt', null, null],
                'pre_call_retry_claim' => ['prepared', 'pre_call', 'retry_same_attempt', null, null],
                'external_reconciliation_claim' => ['prepared', 'post_call_uncertain', null, null, null],
            ][$operation] ?? [];
            $attempt = $value['attempt'];
            $nextAvailableAt = $value['nextAvailableAt'];
            if (
                [
                    [
                        $value['state'],
                        $value['externalCallPhase'],
                        $value['preCallClaimMode'],
                        $value['preCallLookupStatus'],
                        $value['leaseFencingToken'],
                    ] === $required,
                    $attempt <= (['external_submission_claim' => 31][$operation] ?? 32),
                ] !== [true, true]
            ) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
            AuditAnchorTime::assertTransitionTimestamp($trustedNow);
            $initial = [$operation, $attempt] === ['external_submission_claim', 0];
            if (
                [
                    [$nextAvailableAt === null, $initial],
                    AuditAnchorTime::compare($trustedNow, $nextAvailableAt ?? $trustedNow) >= 0,
                ] !== [[$initial, $initial], true]
            ) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
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
            $next['attempt'] = $attempt + (['external_submission_claim' => 1][$operation] ?? 0);
            $next['preCallLookupStatus'] = [
                'external_submission_claim' => 'pending',
                'pre_call_retry_claim' => 'pending',
                'external_reconciliation_claim' => null,
            ][$operation];
            $next['nextAvailableAt'] = null;
            return AuditAnchorTransitionGuard::advance($frame, $expected, $next, $trustedNow, $operation);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }
}
