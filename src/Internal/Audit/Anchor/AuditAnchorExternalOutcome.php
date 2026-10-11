<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final class AuditAnchorExternalOutcome
{
    /**
     * @param array<array-key,mixed> $value
     * @return array{
     *     schemaVersion:int,kind:string,status:'accepted'|'rejected'|'unavailable',reasonCode:?string,
     *     acceptedObjectVersion:?int,acceptedObjectHash:?string,acceptedAnchoredAt:?string,attempt:int
     * }
     */
    public static function result(#[\SensitiveParameter] array $value): array
    {
        try {
            AuditAnchorIntentShape::assertClosed($value, [
                'schemaVersion',
                'kind',
                'status',
                'reasonCode',
                'acceptedObjectVersion',
                'acceptedObjectHash',
                'acceptedAnchoredAt',
                'attempt',
            ]);
            $status = AuditAnchorIntentShape::assertText($value['status']);
            $attempt = self::positiveInteger($value['attempt']);
            if (
                [
                    [$value['schemaVersion'], $value['kind']] === [1, 'audit_anchor_external_result'],
                    in_array($status, ['accepted', 'rejected', 'unavailable'], strict: true),
                    $attempt <= 32,
                ] !== [true, true, true]
            ) {
                throw new \RuntimeException();
            }
            $acceptedObjectVersion = self::nullableInteger($value['acceptedObjectVersion']);
            $acceptedObjectHash = AuditAnchorIntentShape::assertHash($value['acceptedObjectHash'], 'nullable');
            if ($value['acceptedAnchoredAt'] !== null) {
                $acceptedAnchoredAt = AuditAnchorTime::assertTimestamp($value['acceptedAnchoredAt']);
            } else {
                $acceptedAnchoredAt = null;
            }
            $acceptedFieldsPresent =
                $value['acceptedObjectVersion'] !== null
                && $value['acceptedObjectHash'] !== null
                && $value['acceptedAnchoredAt'] !== null;
            $acceptedFieldsEmpty =
                $value['acceptedObjectVersion'] === null
                && $value['acceptedObjectHash'] === null
                && $value['acceptedAnchoredAt'] === null;
            /** @var ?string $reasonCode */
            $reasonCode = $value['reasonCode'];
            $valid = match ($status) {
                'accepted' => $reasonCode === null && $acceptedFieldsPresent,
                'rejected' => in_array($reasonCode, ['predecessor_mismatch', 'permanent_rejection'], strict: true)
                    && $acceptedFieldsEmpty,
                'unavailable' => $reasonCode === 'external_unavailable' && $acceptedFieldsEmpty,
                default => false,
            };
            if (!$valid) {
                throw new \RuntimeException();
            }
            /** @var 'accepted'|'rejected'|'unavailable' $status */
            return [
                'schemaVersion' => 1,
                'kind' => 'audit_anchor_external_result',
                'status' => $status,
                'reasonCode' => $reasonCode,
                'acceptedObjectVersion' => $acceptedObjectVersion,
                'acceptedObjectHash' => $acceptedObjectHash,
                'acceptedAnchoredAt' => $acceptedAnchoredAt,
                'attempt' => $attempt,
            ];
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array<array-key,mixed> $value
     * @return array{
     *     schemaVersion:int,kind:string,
     *     outcome:'external_object_found'|'external_object_absent'|'lookup_unavailable',
     *     foundObject:null|array{
     *         anchorObjectRef:string,anchorObjectVersion:int,acceptedObjectHash:string,
     *         acceptedAnchoredAt:string,segmentId:string,previousAnchorReceiptHash:?string
     *     },
     *     reasonCode:?string
     * }
     */
    public static function reconciliation(#[\SensitiveParameter] array $value): array
    {
        return AuditAnchorReconciliationShape::normalize($value);
    }

    /**
     * @param array<array-key,mixed> $value
     * @return array{
     *     schemaVersion:int,kind:string,
     *     outcome:'external_object_found'|'external_object_absent'|'lookup_unavailable',
     *     foundObject:null|array{
     *         anchorObjectRef:string,anchorObjectVersion:int,acceptedObjectHash:string,
     *         acceptedAnchoredAt:string,segmentId:string,previousAnchorReceiptHash:?string
     *     },
     *     reasonCode:?string
     * }
     */
    public static function lookup(#[\SensitiveParameter] array $value): array
    {
        return self::reconciliation($value);
    }

    private static function nullableInteger(#[\SensitiveParameter] mixed $value): ?int
    {
        return $value === null ? null : self::positiveInteger($value);
    }

    private static function positiveInteger(#[\SensitiveParameter] mixed $value): int
    {
        return AuditAnchorScalar::integer($value, 1, \BlackOps\Internal\Audit\AuditJcsCodec::MAX_SAFE_INTEGER);
    }
}
