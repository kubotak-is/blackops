<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorBinding;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;

final class AuditAnchorFixture
{
    public const string NOW = '2030-01-02T03:04:05.123456Z';
    public const string LATER = '2030-01-02T03:10:05.123456Z';
    public const string SEGMENT = '01941f29-7c00-7000-8000-000000000102';
    public const string CHECKPOINT_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public const string CHECKPOINT_BYTES_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    public const string PREVIOUS_RECEIPT_HASH = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public static function frame(?string $previousReceiptHash = null, string $trustedNow = self::NOW): AuditAnchorFrame
    {
        return AuditAnchorFrame::create(
            [
                'intentId' => 'intent-001',
                'partitionId' => 'system:global',
                'checkpointId' => 'checkpoint-001',
                'checkpointHash' => self::CHECKPOINT_HASH,
                'checkpointBytesHash' => self::CHECKPOINT_BYTES_HASH,
                'anchorStoreRef' => 'anchor-store',
                'anchorObjectRef' => 'object-001',
                'expectedPredecessorVersion' => $previousReceiptHash === null ? null : 4,
                'expectedPredecessorHash' => $previousReceiptHash,
                'conditionalCreateToken' => 'conditional-token-001',
            ],
            AuditAnchorBinding::fromArray([
                'segmentId' => self::SEGMENT,
                'previousAnchorReceiptHash' => $previousReceiptHash,
                'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
                'anchorReceiptId' => 'receipt-001',
            ]),
            'raw-idempotency-key',
            new AuditAnchorExpectation(0, null, null),
            $trustedNow,
        );
    }

    /** @return array<string,mixed> */
    public static function golden(): array
    {
        $path = __DIR__ . '/../../../Fixtures/Audit/audit-anchor-state-v1.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($fixture)) {
            throw new \RuntimeException();
        }
        return $fixture;
    }

    public static function expectation(AuditAnchorFrame $frame): AuditAnchorExpectation
    {
        $value = $frame->intent()->toArray();
        return new AuditAnchorExpectation($value['version'], $value['stateHash'], $value['leaseFencingToken']);
    }

    /** @return array<string,mixed> */
    public static function foundObject(): array
    {
        return [
            'anchorObjectRef' => 'object-001',
            'anchorObjectVersion' => 1,
            'acceptedObjectHash' => self::CHECKPOINT_BYTES_HASH,
            'acceptedAnchoredAt' => '2030-01-02T03:05:05.123456Z',
            'segmentId' => self::SEGMENT,
            'previousAnchorReceiptHash' => null,
        ];
    }

    /** @return array<string,mixed> */
    public static function reconciliation(string $outcome): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_anchor_reconciliation',
            'outcome' => $outcome,
            'foundObject' => $outcome === 'external_object_found' ? self::foundObject() : null,
            'reasonCode' => $outcome === 'lookup_unavailable' ? 'lookup_unavailable' : null,
        ];
    }

    /** @return array<string,mixed> */
    public static function result(string $status, int $attempt = 1): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_anchor_external_result',
            'status' => $status,
            'reasonCode' => match ($status) {
                'accepted', 'unavailable' => $status === 'unavailable' ? 'external_unavailable' : null,
                default => 'permanent_rejection',
            },
            'acceptedObjectVersion' => $status === 'accepted' ? 1 : null,
            'acceptedObjectHash' => $status === 'accepted' ? self::CHECKPOINT_BYTES_HASH : null,
            'acceptedAnchoredAt' => $status === 'accepted' ? '2030-01-02T03:05:05.123456Z' : null,
            'attempt' => $attempt,
        ];
    }
}
