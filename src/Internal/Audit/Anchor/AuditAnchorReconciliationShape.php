<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final class AuditAnchorReconciliationShape
{
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
    public static function normalize(#[\SensitiveParameter] array $value): array
    {
        try {
            AuditAnchorIntentShape::assertClosed($value, [
                'schemaVersion',
                'kind',
                'outcome',
                'foundObject',
                'reasonCode',
            ]);
            if ($value['schemaVersion'] !== 1 || $value['kind'] !== 'audit_anchor_reconciliation') {
                throw new \RuntimeException();
            }
            $outcome = AuditAnchorIntentShape::assertText($value['outcome']);
            if (!in_array(
                $outcome,
                ['external_object_found', 'external_object_absent', 'lookup_unavailable'],
                strict: true,
            )) {
                throw new \RuntimeException();
            }
            $foundObject = null;
            if ($outcome === 'external_object_found') {
                if (!is_array($value['foundObject']) || $value['reasonCode'] !== null) {
                    throw new \RuntimeException();
                }
                /** @var array<array-key,mixed> $found */
                $found = $value['foundObject'];
                AuditAnchorIntentShape::assertClosed($found, [
                    'anchorObjectRef',
                    'anchorObjectVersion',
                    'acceptedObjectHash',
                    'acceptedAnchoredAt',
                    'segmentId',
                    'previousAnchorReceiptHash',
                ]);
                $anchorObjectRef = AuditAnchorIntentShape::assertText($found['anchorObjectRef']);
                $anchorObjectVersion = AuditAnchorScalar::integer(
                    $found['anchorObjectVersion'],
                    1,
                    \BlackOps\Internal\Audit\AuditJcsCodec::MAX_SAFE_INTEGER,
                );
                $acceptedObjectHash = AuditAnchorIntentShape::assertText($found['acceptedObjectHash']);
                AuditAnchorIntentShape::assertHash($acceptedObjectHash);
                $acceptedAnchoredAt = AuditAnchorTime::assertTimestamp($found['acceptedAnchoredAt']);
                self::assertSegment($found['segmentId']);
                $previousAnchorReceiptHash = AuditAnchorIntentShape::assertHash(
                    $found['previousAnchorReceiptHash'],
                    'nullable',
                );
                $foundObject = [
                    'anchorObjectRef' => $anchorObjectRef,
                    'anchorObjectVersion' => $anchorObjectVersion,
                    'acceptedObjectHash' => $acceptedObjectHash,
                    'acceptedAnchoredAt' => $acceptedAnchoredAt,
                    'segmentId' => AuditAnchorIntentShape::assertText($found['segmentId'], 36, 36),
                    'previousAnchorReceiptHash' => $previousAnchorReceiptHash,
                ];
            } else {
                $reasonCode = $outcome === 'lookup_unavailable' ? 'lookup_unavailable' : null;
                if ($value['foundObject'] !== null || $value['reasonCode'] !== $reasonCode) {
                    throw new \RuntimeException();
                }
            }
            /** @var 'external_object_found'|'external_object_absent'|'lookup_unavailable' $outcome */
            /** @var ?string $reasonCode */
            $reasonCode = $value['reasonCode'];
            return [
                'schemaVersion' => 1,
                'kind' => 'audit_anchor_reconciliation',
                'outcome' => $outcome,
                'foundObject' => $foundObject,
                'reasonCode' => $reasonCode,
            ];
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    private static function assertSegment(#[\SensitiveParameter] mixed $value): void
    {
        $segment = AuditAnchorIntentShape::assertText($value, 36, 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $segment) !== 1) {
            throw new \RuntimeException();
        }
    }
}
