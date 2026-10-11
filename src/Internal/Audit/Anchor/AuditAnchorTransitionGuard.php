<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final class AuditAnchorTransitionGuard
{
    public static function assertExpected(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
    ): void {
        $current = $frame->intent()->toArray();
        $expectation = $expected->toArray();
        if (
            [
                $expectation['version'],
                $expectation['stateHash'] !== null,
                hash_equals((string) $expectation['stateHash'], $current['stateHash']),
                ($current['leaseFencingToken'] === null) === ($expectation['token'] === null),
                hash_equals((string) $expectation['token'], (string) $current['leaseFencingToken']),
            ] !== [$current['version'], true, true, true, true]
        ) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
    }

    /** @param array<string,mixed> $candidate */
    public static function advance(
        #[\SensitiveParameter]
        AuditAnchorFrame $frame,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expected,
        #[\SensitiveParameter]
        array $candidate,
        #[\SensitiveParameter]
        string $trustedNow,
        ?string $claimOperation = null,
    ): AuditAnchorFrame {
        try {
            self::assertExpected($frame, $expected);
            $current = $frame->intent()->toArray();
            $currentStateHash = $current['stateHash'];
            $currentUpdatedAt = $current['updatedAt'];
            AuditAnchorTime::assertTransitionTimestamp($trustedNow);
            if (AuditAnchorTime::compare($trustedNow, $currentUpdatedAt) < 0) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
            if ($current['version'] >= \BlackOps\Internal\Audit\AuditJcsCodec::MAX_SAFE_INTEGER) {
                throw new AuditAnchorException(AuditAnchorFailure::ResourceUnavailable);
            }
            AuditAnchorIntent::assertTransitionImmutable($current, $candidate);
            $candidate['version'] = $current['version'] + 1;
            $candidate['previousStateHash'] = $currentStateHash;
            $candidate['updatedAt'] = $trustedNow;
            $candidate['stateHash'] = AuditAnchorCanonical::stateHash($candidate);
            $intent = AuditAnchorIntent::fromArray($candidate);
            $origin = null;
            if ($candidate['leaseFencingToken'] !== null) {
                $origin = $claimOperation === null
                    ? $frame->leaseOrigin()
                    : AuditAnchorLeaseOrigin::fromClaim($claimOperation, $intent);
                if ($origin === null) {
                    throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
                }
            }
            return AuditAnchorFrame::restore([
                'intent' => $intent->toArray(),
                'binding' => $frame->binding()->toArray(),
                'leaseOrigin' => $origin?->toArray(),
            ]);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::ResourceUnavailable);
        }
    }
}
