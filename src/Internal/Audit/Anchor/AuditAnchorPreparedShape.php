<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final class AuditAnchorPreparedShape
{
    /** @param IntentValue $value */
    public static function assert(#[\SensitiveParameter] array $value): void
    {
        $state = $value['state'];
        if ($state === 'prepared') {
            self::prepared($value);
            return;
        }
        if ($state === 'externally_rejected') {
            self::rejected($value);
        }
    }

    /** @param IntentValue $value */
    private static function prepared(#[\SensitiveParameter] array $value): void
    {
        $attempt = $value['attempt'];
        $updatedAt = $value['updatedAt'];
        $nextAvailableAt = $value['nextAvailableAt'];
        $leased = self::leased($value);
        $leaseExpiresAt = $value['leaseExpiresAt'] ?? $updatedAt;
        $leaseValid = AuditAnchorTime::compare($updatedAt, $leaseExpiresAt) <= 0;
        $emptyReceiptFields = array_filter(
            [
                'acceptedObjectVersion',
                'acceptedObjectHash',
                'acceptedAnchoredAt',
                'anchorReceiptId',
                'segmentId',
                'previousAnchorReceiptHash',
                'receiptSigningKeyRef',
                'receiptSignedAt',
                'receiptPreimageHash',
                'receiptBytesHash',
                'anchorReceiptHash',
                'terminalReasonCode',
                'acknowledgedAt',
            ],
            static fn(string $field): bool => $value[$field] !== null,
        ) === [];
        $phase = $value['externalCallPhase'];
        $preCallValid =
            $phase !== 'pre_call'
            || (
                $attempt === 0
                    ? [$value['preCallClaimMode'], $value['preCallLookupStatus'], $nextAvailableAt, $leased] === [
                        'new_attempt',
                        null,
                        null,
                        false,
                    ]
                    : [
                        in_array($value['preCallClaimMode'], ['new_attempt', 'retry_same_attempt'], strict: true),
                        $leased
                            ? in_array($value['preCallLookupStatus'], ['pending', 'absent'], strict: true)
                            : $value['preCallLookupStatus'] === null,
                        $leased ? $nextAvailableAt === null : $nextAvailableAt !== null,
                    ] === [true, true, true]
            );
        $postCallValid =
            $phase !== 'post_call_uncertain'
            || $attempt >= 1
            && [$value['preCallClaimMode'], $value['preCallLookupStatus']] === [null, null]
            && ($leased ? $nextAvailableAt === null : $nextAvailableAt !== null);
        $nextAvailableValid = $nextAvailableAt === null || AuditAnchorTime::compare($updatedAt, $nextAvailableAt) <= 0;
        if (in_array(
            false,
            [
                in_array($phase, ['pre_call', 'post_call_uncertain'], strict: true),
                $leaseValid,
                $emptyReceiptFields,
                $preCallValid,
                $postCallValid,
                $nextAvailableValid,
            ],
            strict: true,
        )) {
            throw new \RuntimeException();
        }
    }

    /** @param IntentValue $value */
    private static function rejected(#[\SensitiveParameter] array $value): void
    {
        $attempt = $value['attempt'];
        $emptyFields = array_filter(
            [
                'acceptedObjectVersion',
                'acceptedObjectHash',
                'acceptedAnchoredAt',
                'anchorReceiptId',
                'segmentId',
                'previousAnchorReceiptHash',
                'receiptSigningKeyRef',
                'receiptSignedAt',
                'receiptPreimageHash',
                'receiptBytesHash',
                'anchorReceiptHash',
                'externalCallPhase',
                'preCallClaimMode',
                'preCallLookupStatus',
                'leaseFencingToken',
                'leaseExpiresAt',
                'nextAvailableAt',
                'acknowledgedAt',
            ],
            static fn(string $field): bool => $value[$field] !== null,
        ) === [];
        $reason = $value['terminalReasonCode'];
        $reasonValid = [
            in_array($reason, ['predecessor_mismatch', 'permanent_rejection', 'attempt_exhausted'], strict: true),
            $reason !== 'attempt_exhausted' || $attempt === 32,
        ] === [true, true];
        if (in_array(false, [$attempt >= 1, $emptyFields, $reasonValid], strict: true)) {
            throw new \RuntimeException();
        }
    }

    /** @param IntentValue $value */
    private static function leased(#[\SensitiveParameter] array $value): bool
    {
        return [$value['leaseFencingToken'] !== null, $value['leaseExpiresAt'] !== null] === [true, true];
    }
}
