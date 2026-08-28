<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditReadContextCodec;
use PHPUnit\Framework\TestCase;

final class AuditReadContextCodecTest extends TestCase
{
    public function testRoundTripPreservesAllReferenceBytes(): void
    {
        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Outcome,
            new AuditTypedReference(' Type/V1 ', 'ID:AbC/01'),
            new AuditTypedReference('execution', 'e'),
            new AuditTypedReference('tenant', 't'),
            '2026-08-28T01:02:03.123456Z',
            'nonce-1',
        );
        $context = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::ResourceUnavailable);
        $codec = new AuditReadContextCodec();
        self::assertSame($context->toArray(), $codec->decodeContext($codec->encodeContext($context))->toArray());
    }

    public function testCompoundInvalidContextUsesTheGlobalStageOrder(): void
    {
        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Outcome,
            new AuditTypedReference('actor', 'a'),
            new AuditTypedReference('execution', 'e'),
            null,
            '2026-08-28T01:02:03.123456Z',
            'nonce-1',
        );
        $codec = new AuditReadContextCodec();
        $canonical = $codec->encodeContext(AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow));
        $wrongDomain = 'blackops.audit.read.request-ref.wrong-domain';
        $wrongPreimage =
            pack('N', strlen($wrongDomain))
            . $wrongDomain
            . pack('N', strlen($codec->encodeBasis($basis)))
            . $codec->encodeBasis($basis);
        $wrongRef = 'oid:v1:' . hash('sha256', $wrongPreimage);
        $wrongCanonical = str_replace($basis->requestRef(), $wrongRef, $canonical);
        $cases = [
            [
                str_replace(
                    ',"execution":{"id":"e","type":"execution"}',
                    '',
                    str_replace('"nonce":"nonce-1"', '"nonce":"' . "\xFF" . '"', $canonical),
                ),
                'shape_invalid',
            ],
            [
                str_replace(
                    '"purpose":"outcome"',
                    '"purpose":"not-a-purpose"',
                    str_replace('"nonce":"nonce-1"', '"nonce":"nonce\\u0000-1"', $canonical),
                ),
                'control_invalid',
            ],
            [
                str_replace(
                    '"purpose":"outcome"',
                    '"purpose":"not-a-purpose"',
                    str_replace('"nonce":"nonce-1"', '"nonce":"e\\u0301"', $canonical),
                ),
                'nfc_invalid',
            ],
            [
                str_replace(',"nonce"', ', "nonce"', $wrongCanonical),
                'order_or_uniqueness_invalid',
            ],
            [
                substr_replace($canonical, ',"target":"self"}', -2, 2),
                'shape_invalid',
            ],
            [
                str_replace($basis->requestRef(), $wrongRef, $canonical),
                'hash_mismatch',
            ],
        ];
        foreach ($cases as $index => [$bytes, $expected]) {
            try {
                $codec->decodeContext($bytes);
                self::fail('compound-invalid context accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code(), 'compound case ' . $index);
            }
        }
    }

    public function testEveryPurposeAndDecisionRoundTrips(): void
    {
        $codec = new AuditReadContextCodec();
        foreach (AuditReadPurpose::cases() as $purpose) {
            $basis = new AuditReadRequestBasis(
                $purpose,
                null,
                new AuditTypedReference('execution', 'e'),
                null,
                '2026-08-28T01:02:03.123456Z',
                'nonce-' . $purpose->value,
            );
            foreach (AuditReadAuthorizationDecision::cases() as $decision) {
                $context = AuditReadContext::fromBasis($basis, $decision);
                self::assertSame(
                    $context->toArray(),
                    $codec->decodeContext($codec->encodeContext($context))->toArray(),
                );
            }
        }
    }

    public function testChangedNonceChangesRequestReference(): void
    {
        $reference = static fn(string $nonce): string => new AuditReadRequestBasis(
            AuditReadPurpose::Status,
            null,
            new AuditTypedReference('execution', 'e'),
            null,
            '2026-08-28T01:02:03.123456Z',
            $nonce,
        )->requestRef();
        self::assertNotSame($reference('nonce-1'), $reference('nonce-2'));
    }
}
