<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit;

use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use PHPUnit\Framework\TestCase;

final class AuditOpaqueIdDeriverTest extends TestCase
{
    public function testGoldenAndTenantClosedContext(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(implode('', array_map(
            static fn(int $i): string => chr($i),
            range(0, 31),
        )));
        self::assertSame(
            'oid:v1:5272d9f8fc05768f7e4bd0cc0ef86729e2855fafaa00c2d5af6626e417b57f5b',
            (string) $deriver->derive(AuditOpaqueIdPurpose::Resource, 'operation-123'),
        );
        self::assertNotSame(
            (string) $deriver->deriveTenant(new AuditTypedReference('tenant', 't1')),
            (string) $deriver->deriveTenant(new AuditTypedReference('tenant', 't2')),
        );
        self::assertSame(
            'oid:v1:6f8ddcb879201ece4e44eab6583ba21d43348e66db3177ecb3fe23c342437b9b',
            (string) $deriver->deriveTenant(['id' => 'ID:AbC/01', 'type' => ' Type/V1 ']),
        );
        self::assertSame(
            'oid:v1:126af91d210237e27ea45de3c9f79a4046d51cf9316fd99205e379868ce2bd2a',
            (string) $deriver->deriveEvidence([
                'value' => 'ok',
                'fieldName' => 'result_code',
                'evidenceVersion' => 1,
                'eventVersion' => 1,
                'eventType' => 'security.authentication.succeeded',
                'catalogHash' => 'cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83',
            ]),
        );
    }

    public function testStructuredPurposesCannotUseScalarOrMalformedContext(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        foreach ([
            fn(): object => $deriver->derive(AuditOpaqueIdPurpose::Tenant, 'tenant'),
            fn(): object => $deriver->derive(AuditOpaqueIdPurpose::Evidence, 'evidence'),
            fn(): object => $deriver->deriveTenant(['type' => 'tenant', 'id' => 't', 'extra' => 'x']),
            fn(): object => $deriver->deriveEvidence([
                'catalogHash' => str_repeat('a', 64),
                'eventType' => 'event',
                'eventVersion' => 1,
                'evidenceVersion' => 1,
                'fieldName' => 'field',
                'value' => new \stdClass(),
            ]),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('malformed structured context accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
        foreach ([AuditOpaqueIdPurpose::Tenant, AuditOpaqueIdPurpose::Evidence] as $purpose) {
            try {
                $deriver->derive($purpose, "context\xFF");
                self::fail('invalid UTF-8 structured scalar accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('utf8_invalid', $exception->code());
            }
        }
        $evidence = [
            'catalogHash' => str_repeat('a', 64),
            'eventType' => 'security.event',
            'eventVersion' => 1,
            'evidenceVersion' => 1,
            'fieldName' => 'result_code',
            'value' => 'ok',
        ];
        foreach ([
            [['eventType' => 'Bad!'], 'grammar_invalid'],
            [['fieldName' => 'Bad!'], 'grammar_invalid'],
            [['eventVersion' => 0], 'semantic_invalid'],
            [['evidenceVersion' => 0], 'semantic_invalid'],
            [['value' => str_repeat('x', 257)], 'byte_bound_invalid'],
        ] as [$mutation, $code]) {
            try {
                $deriver->deriveEvidence([...$evidence, ...$mutation]);
                self::fail('invalid evidence context accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($code, $exception->code());
            }
        }
    }

    public function testEvidenceContextValidatesEveryTextSlotByStage(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        $context = [
            'catalogHash' => 'not-a-hash',
            'eventType' => "event\xFF",
            'eventVersion' => 1,
            'evidenceVersion' => 1,
            'fieldName' => 'field',
            'value' => 'ok',
        ];
        try {
            $deriver->deriveEvidence($context);
            self::fail('compound-invalid evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
    }

    public function testUnsafeStructuredIntegersAreShapeBeforeTextStages(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        $base = [
            'catalogHash' => "hash\xFF",
            'eventType' => 'event',
            'eventVersion' => 1,
            'evidenceVersion' => 1,
            'fieldName' => 'field',
            'value' => 'ok',
        ];
        foreach ([
            [...$base, 'eventVersion' => 9_007_199_254_740_992],
            [...$base, 'value' => 9_007_199_254_740_992],
        ] as $context) {
            try {
                $deriver->deriveEvidence($context);
                self::fail('unsafe structured integer accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }

    public function testTenantReferencePreservesExactMembersAndRejectsEveryUnsafeBoundary(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        self::assertNotSame(
            (string) $deriver->deriveTenant(['type' => 't', 'id' => 'i']),
            (string) $deriver->deriveTenant([
                'type' => str_repeat('t', 256),
                'id' => str_repeat('i', 256),
            ]),
        );
        self::assertNotSame(
            (string) $deriver->deriveTenant(['type' => 't', 'id' => 'i']),
            (string) $deriver->deriveTenant(['type' => 't', 'id' => 'I']),
        );
        self::assertNotSame(
            (string) $deriver->deriveTenant(['type' => ' Type/V1 ', 'id' => 'ID:AbC/01']),
            (string) $deriver->deriveTenant(['type' => 'type/v1', 'id' => 'id:abc/01']),
        );
        foreach ([
            ['type' => '', 'id' => 'i'],
            ['type' => str_repeat('t', 257), 'id' => 'i'],
            ['type' => 't', 'id' => str_repeat('i', 257)],
            ['type' => "\xFF", 'id' => 'i'],
            ['type' => "e\u{0301}", 'id' => 'i'],
            ['type' => "t\u{0001}", 'id' => 'i'],
            ['type' => "t\u{200B}", 'id' => 'i'],
            ['type' => 't', 'id' => "e\u{0301}"],
            ['type' => 't', 'id' => "i\u{0001}"],
            ['type' => 't', 'id' => "i\u{200B}"],
            ['type' => 't', 'id' => "\xFF"],
            ['id' => 'i'],
            ['type' => 't'],
            ['type' => 1, 'id' => 'i'],
            ['type' => 't', 'id' => 1],
        ] as $context) {
            try {
                $deriver->deriveTenant($context);
                self::fail('unsafe tenant reference accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDedicatedKeyLengthBoundaryIsClosed(): void
    {
        foreach ([0, 31, 33] as $length) {
            try {
                new HmacAuditOpaqueIdDeriver(str_repeat("\x01", $length));
                self::fail('invalid key length accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('byte_bound_invalid', $exception->code());
            }
        }
        new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
    }

    public function testInvalidTenantTraceDoesNotLeakSensitiveMembers(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        $canary = 'tenant-secret-canary-9f3b';
        try {
            $deriver->deriveTenant(['type' => 'tenant', 'id' => $canary, 'unexpected' => true]);
            self::fail('invalid tenant context accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
            self::assertStringNotContainsString($canary, $exception->getMessage());
        }
    }

    public function testOpaquePurposeInventoryIsClosed(): void
    {
        self::assertSame(
            [
                'record',
                'action',
                'principal',
                'execution',
                'resource',
                'tenant',
                'operation',
                'correlation',
                'causation',
                'trace',
                'span',
                'evidence',
            ],
            array_map(
                static fn(AuditOpaqueIdPurpose $purpose): string => $purpose->value,
                AuditOpaqueIdPurpose::cases(),
            ),
        );
    }

    public function testKeyAndContextAreNotSerialized(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat("\x01", 32));
        self::assertSame([], $deriver->__debugInfo());
        self::assertSame([], $deriver->__serialize());
        try {
            unserialize(serialize($deriver));
            self::fail('opaque deriver serialized');
        } catch (\LogicException) {
            self::addToAssertionCount(1);
        }
    }
}
