<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditCatalogHash;
use BlackOps\Core\Audit\AuditOpaqueId;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditReadContextCodec;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use PHPUnit\Framework\TestCase;

final class AuditValueTypesTest extends TestCase
{
    public function testPreservesMixedCaseSpaceSlashAndColonExactly(): void
    {
        $reference = new AuditTypedReference(' Type/V1 ', 'ID:AbC/01');
        self::assertSame(['type' => ' Type/V1 ', 'id' => 'ID:AbC/01'], $reference->toArray());
        self::assertNotSame($reference->toArray(), new AuditTypedReference('type/v1', 'id:abc/01')->toArray());
        self::assertSame(12, count(AuditOpaqueIdPurpose::cases()));
    }

    public function testTypedReferenceBoundsAndUnicodeAreClosed(): void
    {
        foreach (['type', 'id'] as $member) {
            foreach (['', str_repeat('x', 257), "a\u{0301}", "a\u{0000}", "a\u{200B}", "\xFF"] as $value) {
                $type = $member === 'type' ? $value : 'type';
                $id = $member === 'id' ? $value : 'id';
                try {
                    new AuditTypedReference($type, $id);
                    self::fail('invalid reference accepted');
                } catch (AuditValidationException) {
                    self::addToAssertionCount(1);
                }
            }
        }
        new AuditTypedReference(str_repeat('x', 256), str_repeat('y', 256));
    }

    public function testTypedReferenceValidatesBothMembersByStage(): void
    {
        try {
            new AuditTypedReference(str_repeat('x', 257), "id\xFF");
            self::fail('compound-invalid reference accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
    }

    public function testFixedIdentifiersRunTextAndByteStagesBeforeGrammar(): void
    {
        foreach ([
            [AuditCatalogHash::class, 'a' . "\xFF" . str_repeat('a', 62), 'utf8_invalid'],
            [AuditCatalogHash::class, str_repeat('a', 63), 'byte_bound_invalid'],
            [AuditCatalogHash::class, str_repeat('a', 65), 'byte_bound_invalid'],
            [AuditCatalogHash::class, "a\u{0001}" . str_repeat('a', 62), 'control_invalid'],
            [AuditCatalogHash::class, "e\u{0301}" . str_repeat('a', 62), 'nfc_invalid'],
            [AuditOpaqueId::class, 'o' . "\xFF" . str_repeat('a', 69), 'utf8_invalid'],
            [AuditOpaqueId::class, str_repeat('a', 70), 'byte_bound_invalid'],
            [AuditOpaqueId::class, str_repeat('a', 72), 'byte_bound_invalid'],
            [AuditOpaqueId::class, "o\u{0001}" . str_repeat('a', 69), 'control_invalid'],
            [AuditOpaqueId::class, "e\u{0301}" . str_repeat('a', 69), 'nfc_invalid'],
        ] as [$class, $value, $failure]) {
            try {
                new $class($value);
                self::fail('invalid fixed identifier accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($failure, $exception->code());
            }
        }
        new AuditCatalogHash(str_repeat('a', 64));
        new AuditOpaqueId('oid:v1:' . str_repeat('a', 64));
    }

    public function testVariantsRemainDistinctAcrossRequestAndTenantConsumers(): void
    {
        $original = new AuditTypedReference(' Type/V1 ', 'ID:AbC/01');
        $trimmed = new AuditTypedReference('Type/V1', 'ID:AbC/01');
        $folded = new AuditTypedReference(' Type/V1 ', 'id:abc/01');
        $basis = static fn(AuditTypedReference $actor): AuditReadRequestBasis => new AuditReadRequestBasis(
            AuditReadPurpose::Status,
            $actor,
            new AuditTypedReference('execution', 'exec'),
            null,
            '2026-08-20T03:20:00.123456Z',
            'nonce-1',
        );
        self::assertNotSame($original->toArray(), $trimmed->toArray());
        self::assertNotSame($original->toArray(), $folded->toArray());
        self::assertNotSame(
            AuditReadContextCodec::requestRef($basis($original)),
            AuditReadContextCodec::requestRef($basis($trimmed)),
        );
        self::assertNotSame(
            AuditReadContextCodec::requestRef($basis($original)),
            AuditReadContextCodec::requestRef($basis($folded)),
        );
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        self::assertNotSame((string) $deriver->deriveTenant($original), (string) $deriver->deriveTenant($trimmed));
        self::assertNotSame((string) $deriver->deriveTenant($original), (string) $deriver->deriveTenant($folded));
    }
}
