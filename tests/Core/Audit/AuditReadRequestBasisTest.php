<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditReadContextCodec;
use PHPUnit\Framework\TestCase;

final class AuditReadRequestBasisTest extends TestCase
{
    public function testNullGoldenAndPreservationCanary(): void
    {
        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Status,
            null,
            new AuditTypedReference('cli', 'exec-1'),
            null,
            '2026-08-20T03:20:00.123456Z',
            'nonce-1',
        );
        $codec = new AuditReadContextCodec();
        self::assertSame($basis->toArray(), $codec->decodeBasis($codec->encodeBasis($basis))->toArray());
        self::assertSame(
            'oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf',
            AuditReadContextCodec::requestRef($basis),
        );
        self::assertSame($basis->requestRef(), AuditReadContextCodec::requestRef($basis));
    }

    public function testUnicodeSeparatorsUseOneJcsAuthorityInEveryReferenceSlot(): void
    {
        $reference = new AuditTypedReference("actor\u{2028}", "tenant\u{2029}");
        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Diagnostics,
            $reference,
            new AuditTypedReference("execution\u{2029}", "exec\u{2028}"),
            $reference,
            '2026-08-20T03:20:00.123456Z',
            "nonce\u{2028}\u{2029}",
        );
        $codec = new AuditReadContextCodec();
        $json = $codec->encodeBasis($basis);
        self::assertStringContainsString("\u{2028}", $json);
        self::assertStringContainsString("\u{2029}", $json);
        self::assertStringNotContainsString('\\u2028', $json);
        self::assertStringNotContainsString('\\u2029', $json);
        self::assertSame($json, $codec->encodeBasis($codec->decodeBasis($json)));
        self::assertSame($basis->requestRef(), AuditReadContextCodec::requestRef($basis));
    }

    public function testRequestedAtAndNonceBoundariesAreExact(): void
    {
        $reference = new AuditTypedReference('execution', 'e');
        $valid = static function (string $requestedAt, string $nonce) use ($reference): AuditReadRequestBasis {
            return new AuditReadRequestBasis(AuditReadPurpose::Status, null, $reference, null, $requestedAt, $nonce);
        };
        $valid('2026-08-20T03:20:00.123456Z', 'x');
        $valid('2026-08-20T03:20:00.123456Z', str_repeat('x', 128));
        foreach ([str_repeat('x', 26), str_repeat('x', 28)] as $requestedAt) {
            try {
                $valid($requestedAt, 'x');
                self::fail('requestedAt boundary accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('byte_bound_invalid', $exception->code());
            }
        }
        foreach (['', str_repeat('x', 129)] as $nonce) {
            try {
                $valid('2026-08-20T03:20:00.123456Z', $nonce);
                self::fail('nonce boundary accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('byte_bound_invalid', $exception->code());
            }
        }
        foreach (["nonce-\u{0001}", "e\u{0301}"] as $nonce) {
            try {
                $valid('2026-08-20T03:20:00.123456Z', $nonce);
                self::fail('unsafe nonce accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame(
                    str_contains($nonce, "\u{0001}") ? 'control_invalid' : 'nfc_invalid',
                    $exception->code(),
                );
            }
        }
        foreach (["requestedAt\xFF", "requestedAt\u{0001}", "e\u{0301}", str_repeat('x', 129)] as $nonce) {
            try {
                $valid(str_repeat('x', 27), $nonce);
                self::fail('compound basis accepted');
            } catch (AuditValidationException $exception) {
                $expected = match (true) {
                    $nonce === "requestedAt\xFF" => 'utf8_invalid',
                    $nonce === "requestedAt\u{0001}" => 'control_invalid',
                    $nonce === "e\u{0301}" => 'nfc_invalid',
                    default => 'byte_bound_invalid',
                };
                self::assertSame($expected, $exception->code());
            }
        }
    }
}
