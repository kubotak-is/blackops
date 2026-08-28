<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use PHPUnit\Framework\TestCase;

final class AuditEventCatalogCodecTest extends TestCase
{
    public function testRoundTripAndCanonicalLineEnding(): void
    {
        $codec = new AuditEventCatalogCodec();
        $catalog = FrameworkAuditEventCatalog::catalog();
        $bytes = $codec->encode($catalog);
        self::assertStringEndsWith("\n", $bytes);
        self::assertSame($catalog->catalogHash, $codec->decode($bytes)->catalogHash);
    }

    public function testRawCapPrecedesMalformedDecode(): void
    {
        $codec = new AuditEventCatalogCodec();
        try {
            $codec->decode(str_repeat('x', AuditEventCatalogCodec::RAW_MAX_BYTES + 1));
            self::fail();
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
    }

    public function testCanonicalShapedRawOverCapIsRejectedBeforeDecode(): void
    {
        $jcs = new AuditJcsCodec();
        $base = [
            'schemaVersion' => 1,
            'kind' => 'audit_event_catalog',
            'catalogId' => '',
            'catalogVersion' => 1,
            'owner' => 'framework',
            'events' => [],
            'catalogHash' => str_repeat('0', 64),
        ];
        $prefix = strlen($jcs->canonicalJson($base));
        $base['catalogId'] = str_repeat('a', AuditEventCatalogCodec::RAW_MAX_BYTES - $prefix);
        $raw = $jcs->canonicalJson($base) . "\n";
        self::assertSame(AuditEventCatalogCodec::RAW_MAX_BYTES + 1, strlen($raw));
        $decoder = new class extends AuditJcsCodec {
            public int $calls = 0;

            public function decode(string $bytes): mixed
            {
                ++$this->calls;
                return parent::decode($bytes);
            }
        };
        try {
            new AuditEventCatalogCodec($decoder)->decode($raw);
            self::fail('canonical-shaped over-cap input accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
        self::assertSame(0, $decoder->calls);
    }

    public function testUnsignedCompleteAndRawCapsAreInclusive(): void
    {
        $jcs = new class extends AuditJcsCodec {
            /** @var list<int> */
            public array $lengths = [];

            public function canonicalJson(mixed $value): string
            {
                if (is_array($value) && array_key_exists('catalogHash', $value)) {
                    $result = $this->canonicalObject(AuditEventCatalogCodec::COMPLETE_MAX_BYTES);
                    $this->lengths[] = strlen($result);
                    return $result;
                }
                $result = $this->canonicalObject(AuditEventCatalogCodec::UNSIGNED_MAX_BYTES);
                $this->lengths[] = strlen($result);
                return $result;
            }

            private function canonicalObject(int $length): string
            {
                $prefix = '{"padding":"';
                $suffix = '"}';
                return $prefix . str_repeat('x', $length - strlen($prefix) - strlen($suffix)) . $suffix;
            }
        };
        $framework = FrameworkAuditEventCatalog::catalog();
        $codec = new AuditEventCatalogCodec($jcs);
        $catalog = $codec->catalog('framework.boundary', 1, \BlackOps\Core\Audit\AuditSourceOwner::Framework, [
            $framework->events[0],
        ]);
        self::assertSame(
            [
                AuditEventCatalogCodec::UNSIGNED_MAX_BYTES,
                AuditEventCatalogCodec::COMPLETE_MAX_BYTES,
            ],
            $jcs->lengths,
        );
        self::assertSame(AuditEventCatalogCodec::RAW_MAX_BYTES, strlen($codec->encode($catalog)));
        self::assertSame(
            [
                AuditEventCatalogCodec::UNSIGNED_MAX_BYTES,
                AuditEventCatalogCodec::COMPLETE_MAX_BYTES,
                AuditEventCatalogCodec::UNSIGNED_MAX_BYTES,
                AuditEventCatalogCodec::COMPLETE_MAX_BYTES,
            ],
            $jcs->lengths,
        );
    }

    public function testRawCapPrecedesEveryDefectWithoutCallingDecoder(): void
    {
        $decoder = new class extends AuditJcsCodec {
            public int $calls = 0;

            public function decode(string $bytes): mixed
            {
                ++$this->calls;
                return parent::decode($bytes);
            }
        };
        $codec = new AuditEventCatalogCodec($decoder);
        foreach ([
            str_repeat('x', AuditEventCatalogCodec::RAW_MAX_BYTES + 1),
            str_repeat('{"a":1,"a":2}', 81_000),
            str_repeat("\xFF", AuditEventCatalogCodec::RAW_MAX_BYTES + 1),
        ] as $bytes) {
            try {
                $codec->decode($bytes);
                self::fail('over-cap input accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('jcs_bound_invalid', $exception->code());
            }
        }
        self::assertSame(0, $decoder->calls);
    }

    public function testAtCapMalformedAndEmptyObjectArrayShapeAreStageOneFailures(): void
    {
        $codec = new AuditEventCatalogCodec();
        try {
            $codec->decode(str_repeat('x', AuditEventCatalogCodec::RAW_MAX_BYTES));
            self::fail('malformed input accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
        $raw =
            '{"schemaVersion":1,"kind":"audit_event_catalog","catalogId":"x","catalogVersion":1,"owner":"framework","events":{},"catalogHash":"'
            . str_repeat('0', 64)
            . '"}'
            . "\n";
        try {
            $codec->decode($raw);
            self::fail('object accepted as event list');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testStoredCatalogRejectsBomWhitespaceAndCrLfSpellings(): void
    {
        $fixture = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/framework-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        foreach ([
            ["\xEF\xBB\xBF" . $fixture, 'shape_invalid'],
            ["\n" . $fixture, 'order_or_uniqueness_invalid'],
            [$fixture . ' ', 'order_or_uniqueness_invalid'],
            [str_replace("\n", "\r\n", $fixture), 'order_or_uniqueness_invalid'],
        ] as [$bytes, $expected]) {
            try {
                new AuditEventCatalogCodec()->decode($bytes);
                self::fail('non-canonical catalog spelling accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code());
            }
        }
    }

    public function testClosedShapePrecedesInvalidUtf8AcrossTheDecodedTree(): void
    {
        $fixture = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/framework-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        $raw = str_replace('"catalogHash":', '"unknown' . "\xFF" . '":', $fixture);
        try {
            new AuditEventCatalogCodec()->decode($raw);
            self::fail('invalid catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testSemanticStagesRunAcrossTheWholeTreeBeforeHydration(): void
    {
        $fixture = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/framework-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        $grammarAndSemantic = str_replace(
            '"catalogId":"blackops.framework.security_access"',
            '"catalogId":"Bad"',
            $fixture,
        );
        $grammarAndSemantic = str_replace('"category":"access"', '"category":"not_a_category"', $grammarAndSemantic);
        try {
            new AuditEventCatalogCodec()->decode($grammarAndSemantic);
            self::fail('invalid catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
        $shapeAndSemantic = str_replace('"category":"access"', '"category":"not_a_category"', $fixture);
        $position = strpos($shapeAndSemantic, '"version":1');
        self::assertIsInt($position);
        $shapeAndSemantic = substr_replace($shapeAndSemantic, '"version":"wrong"', $position, strlen('"version":1'));
        try {
            new AuditEventCatalogCodec()->decode($shapeAndSemantic);
            self::fail('invalid catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testDirectCatalogDefersReservedNamespaceUntilGlobalGrammarPass(): void
    {
        $framework = FrameworkAuditEventCatalog::catalog();
        $event = $framework->events[0];
        try {
            new AuditEventCatalogCodec()->catalog(
                'Bad!',
                1,
                \BlackOps\Core\Audit\AuditSourceOwner::Application,
                [$event],
            );
            self::fail('invalid direct catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    public function testDirectCatalogSemanticPassPrecedesUnsignedJcsCap(): void
    {
        $framework = FrameworkAuditEventCatalog::catalog();
        $jcs = new class extends AuditJcsCodec {
            public function canonicalJson(mixed $value): string
            {
                return str_repeat('x', AuditEventCatalogCodec::UNSIGNED_MAX_BYTES + 1);
            }
        };
        try {
            new AuditEventCatalogCodec($jcs)->catalog(
                'application.catalog',
                1,
                \BlackOps\Core\Audit\AuditSourceOwner::Application,
                [$framework->events[0]],
            );
            self::fail('reserved namespace escaped the direct semantic pass');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testEvidencePresenceNullabilityAndFlatPrimitiveMatrix(): void
    {
        $definition = new AuditEventDefinition(
            'application.evidence',
            1,
            1,
            AuditEventCategory::Business,
            ['application.evidence'],
            [new AuditSemanticTuple(
                'application.evidence',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [
                new AuditEvidenceFieldDefinition(
                    'code',
                    AuditEvidenceType::String,
                    true,
                    false,
                    16,
                    AuditEvidenceClassification::SafeCode,
                ),
                new AuditEvidenceFieldDefinition('count', AuditEvidenceType::Integer, true, true),
                new AuditEvidenceFieldDefinition('flag', AuditEvidenceType::Boolean, false, true),
            ],
            8192,
            AuditDelivery::Required,
        );
        $codec = new AuditEventCatalogCodec();
        foreach ([
            ['version' => 1, 'code' => 'ok', 'count' => 2],
            ['version' => 1, 'code' => 'ok', 'count' => null, 'flag' => null],
            ['version' => 1, 'code' => 'ok', 'count' => 2, 'flag' => false],
        ] as $evidence) {
            $codec->validateEvidence($definition, $evidence);
            self::addToAssertionCount(1);
        }
        foreach ([
            ['version' => 1, 'count' => 2],
            ['version' => 1, 'code' => null, 'count' => 2],
            ['version' => 1, 'code' => 'ok', 'count' => '2'],
            ['version' => 1, 'code' => 'ok', 'count' => 2, 'unknown' => true],
            ['version' => 1, 'code' => 'ok', 'count' => 2, 'flag' => []],
        ] as $evidence) {
            try {
                $codec->validateEvidence($definition, $evidence);
                self::fail('invalid evidence accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEvidenceRejectsNestedListFloatAndNonFiniteValuesAsShape(): void
    {
        $definition = new AuditEventDefinition(
            'application.evidence_shape',
            1,
            1,
            AuditEventCategory::Business,
            ['application.evidence_shape'],
            [new AuditSemanticTuple(
                'application.evidence_shape',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [new AuditEvidenceFieldDefinition(
                'value',
                AuditEvidenceType::String,
                true,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            )],
            8192,
            AuditDelivery::Required,
        );
        foreach ([
            ['value' => ['nested']],
            ['value' => ['nested' => true]],
            ['value' => 1.5],
            ['value' => INF],
            ['value' => NAN],
        ] as $evidence) {
            try {
                new AuditEventCatalogCodec()->validateEvidence($definition, ['version' => 1, ...$evidence]);
                self::fail('unsupported evidence value accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }

    public function testUnknownEvidenceMemberPrecedesVersionMismatch(): void
    {
        $definition = new AuditEventDefinition(
            'application.evidence',
            1,
            1,
            AuditEventCategory::Business,
            ['application.evidence'],
            [new AuditSemanticTuple(
                'application.evidence',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [],
            13,
            AuditDelivery::Required,
        );
        try {
            new AuditEventCatalogCodec()->validateEvidence($definition, [
                'version' => 2,
                'unexpected' => true,
            ]);
            self::fail('compound-invalid evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testEvidenceJcsByteBoundariesAreInclusive(): void
    {
        $fields = [];
        for ($index = 1; $index <= 32; ++$index)
            $fields[] = new AuditEvidenceFieldDefinition(
                'field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                AuditEvidenceType::String,
                false,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            );
        $definition = new AuditEventDefinition(
            'application.evidence',
            1,
            1,
            AuditEventCategory::Business,
            ['application.evidence'],
            [new AuditSemanticTuple(
                'application.evidence',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            $fields,
            8192,
            AuditDelivery::Required,
        );
        $codec = new AuditEventCatalogCodec();
        $evidenceAtCap = $this->evidenceWithJcsLength(8192);
        $codec->validateEvidence($definition, $evidenceAtCap);
        $evidenceOverCap = $this->evidenceWithJcsLength(8193);
        try {
            $codec->validateEvidence($definition, $evidenceOverCap);
            self::fail('over-cap evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
    }

    public function testOpaqueIdEvidenceBoundaryIsInclusive(): void
    {
        $definition = new AuditEventDefinition(
            'application.opaque',
            1,
            1,
            AuditEventCategory::Business,
            ['application.opaque'],
            [new AuditSemanticTuple(
                'application.opaque',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [new AuditEvidenceFieldDefinition(
                'opaque_id',
                AuditEvidenceType::String,
                true,
                false,
                71,
                AuditEvidenceClassification::OpaqueId,
            )],
            8192,
            AuditDelivery::Required,
        );
        $codec = new AuditEventCatalogCodec();
        $valid = 'oid:v1:' . str_repeat('a', 64);
        $codec->validateEvidence($definition, ['version' => 1, 'opaque_id' => $valid]);
        try {
            $codec->validateEvidence($definition, ['version' => 1, 'opaque_id' => 'oid:v1:' . str_repeat('a', 63)]);
            self::fail('70-byte opaque evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
        try {
            $codec->validateEvidence($definition, ['version' => 1, 'opaque_id' => 'oid:v1:' . str_repeat('a', 65)]);
            self::fail('72-byte opaque evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('byte_bound_invalid', $exception->code());
        }
    }

    public function testDeclaredStringEvidenceValueBoundaryIsInclusive(): void
    {
        $definition = new AuditEventDefinition(
            'application.string_boundary',
            1,
            1,
            AuditEventCategory::Business,
            ['application.string_boundary'],
            [new AuditSemanticTuple(
                'application.string_boundary',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [new AuditEvidenceFieldDefinition(
                'code',
                AuditEvidenceType::String,
                true,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            )],
            8192,
            AuditDelivery::Required,
        );
        $codec = new AuditEventCatalogCodec();
        foreach ([1, 256] as $length)
            $codec->validateEvidence($definition, ['version' => 1, 'code' => str_repeat('x', $length)]);
        foreach ([0, 257] as $length) {
            try {
                $codec->validateEvidence($definition, ['version' => 1, 'code' => str_repeat('x', $length)]);
                self::fail('invalid string evidence bound accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('byte_bound_invalid', $exception->code());
            }
        }
    }

    public function testEvidenceAllPresenceNullabilityRowsAndStagePrecedence(): void
    {
        $fields = [];
        foreach ([AuditEvidenceType::String, AuditEvidenceType::Integer, AuditEvidenceType::Boolean] as $type) {
            $prefix = match ($type) {
                AuditEvidenceType::String => 'string',
                AuditEvidenceType::Integer => 'integer',
                AuditEvidenceType::Boolean => 'boolean',
            };
            foreach ([
                ['required',          true,  false],
                ['required_nullable', true,  true],
                ['optional',          false, false],
                ['optional_nullable', false, true],
            ] as [$suffix, $required, $nullable]) {
                $fields[] = new AuditEvidenceFieldDefinition(
                    $prefix . '_' . $suffix,
                    $type,
                    $required,
                    $nullable,
                    $type === AuditEvidenceType::String ? 16 : null,
                    $type === AuditEvidenceType::String ? AuditEvidenceClassification::SafeCode : null,
                );
            }
        }
        usort($fields, static fn(
            AuditEvidenceFieldDefinition $left,
            AuditEvidenceFieldDefinition $right,
        ): int => strcmp($left->name, $right->name));
        $definition = new AuditEventDefinition(
            'application.evidence',
            1,
            1,
            AuditEventCategory::Business,
            ['application.evidence'],
            [new AuditSemanticTuple(
                'application.evidence',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            $fields,
            8192,
            AuditDelivery::Required,
        );
        $values = ['string' => 'ok', 'integer' => 1, 'boolean' => true];
        $base = ['version' => 1];
        foreach ($fields as $field) {
            if ($field->required)
                $base[$field->name] = $values[explode('_', $field->name, 2)[0]];
        }
        $codec = new AuditEventCatalogCodec();
        $codec->validateEvidence($definition, $base);
        foreach ($fields as $field) {
            $missing = $base;
            unset($missing[$field->name]);
            if ($field->required) {
                try {
                    $codec->validateEvidence($definition, $missing);
                    self::fail('required evidence field missing was accepted');
                } catch (AuditValidationException $exception) {
                    self::assertSame('semantic_invalid', $exception->code());
                }
            } else {
                $codec->validateEvidence($definition, $missing);
            }
            $null = $base;
            $null[$field->name] = null;
            if ($field->nullable) {
                $codec->validateEvidence($definition, $null);
            } else {
                try {
                    $codec->validateEvidence($definition, $null);
                    self::fail('non-nullable evidence field accepted null');
                } catch (AuditValidationException $exception) {
                    self::assertSame('semantic_invalid', $exception->code());
                }
            }
        }
        foreach ($fields as $field) {
            $wrong = $base;
            $wrong[$field->name] = $field->type === AuditEvidenceType::String ? 1 : 'wrong';
            try {
                $codec->validateEvidence($definition, $wrong);
                self::fail('wrong evidence scalar accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
        $invalidUtf8 = $base;
        $invalidUtf8['version'] = 2;
        $invalidUtf8['string_required'] = "\xFF";
        try {
            $codec->validateEvidence($definition, $invalidUtf8);
            self::fail('invalid UTF-8 evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
        $invalidGrammar = $base;
        $invalidGrammar['version'] = 2;
        $invalidGrammar['string_required'] = 'Bad!';
        try {
            $codec->validateEvidence($definition, $invalidGrammar);
            self::fail('invalid evidence grammar accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
        $invalidBounds = $base;
        $invalidBounds['version'] = 2;
        $invalidBounds['string_required'] = str_repeat('x', 17);
        try {
            $codec->validateEvidence($definition, $invalidBounds);
            self::fail('over-bound evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('byte_bound_invalid', $exception->code());
        }
        $shapeAfterText = $base;
        $shapeAfterText['version'] = 2;
        $shapeAfterText['string_required'] = "ok\xFF";
        $shapeAfterText['integer_required'] = 1.5;
        try {
            $codec->validateEvidence($definition, $shapeAfterText);
            self::fail('shape-invalid evidence accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testEachSemanticTupleAndBindingMemberHasAnIndependentFailureBoundary(): void
    {
        $fixture = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/framework-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        foreach ([
            ['"actionType":"security.authentication.succeeded"', '"actionType":"Bad!"',                 'grammar_invalid'],
            ['"phase":"succeeded"',                              '"phase":"unknown_phase"',             'semantic_invalid'],
            ['"resourceType":"authentication"',                  '"resourceType":"Bad!"',               'grammar_invalid'],
            ['"outcomeStatus":"succeeded"',                      '"outcomeStatus":"unknown_status"',    'semantic_invalid'],
            ['"outcomeCode":"authenticated"',                    '"outcomeCode":"Bad!"',                'grammar_invalid'],
            ['"policyDecision":"allow"',                         '"policyDecision":"unknown_decision"', 'semantic_invalid'],
            ['"policyReasonCode":"authenticated"',               '"policyReasonCode":"Bad!"',           'grammar_invalid'],
            ['"owner":"framework"',                              '"owner":"unknown_owner"',             'semantic_invalid'],
            ['"category":"access"',                              '"category":"unknown_category"',       'semantic_invalid'],
            ['"delivery":"durable"',                             '"delivery":"best_effort"',            'semantic_invalid'],
            ['"evidenceVersion":1',                              '"evidenceVersion":0',                 'semantic_invalid'],
        ] as [$needle, $replacement, $failure]) {
            $position = strpos($fixture, $needle);
            self::assertIsInt($position);
            $mutated = substr_replace($fixture, $replacement, $position, strlen($needle));
            try {
                new AuditEventCatalogCodec()->decode($mutated);
                self::fail('single-member mutation accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($failure, $exception->code());
            }
        }
    }

    /** @return array<string,mixed> */
    private function evidenceWithJcsLength(int $target): array
    {
        $jcs = new AuditJcsCodec();
        for ($count = 1; $count <= 32; ++$count) {
            for ($length = 1; $length <= 256; ++$length) {
                $evidence = ['version' => 1];
                for ($index = 1; $index <= $count; ++$index)
                    $evidence['field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)] = str_repeat(
                        'x',
                        $index === $count ? $length : 256,
                    );
                if (strlen($jcs->canonicalJson($evidence)) === $target)
                    return $evidence;
            }
        }
        throw new \LogicException('no evidence boundary vector found');
    }
}
