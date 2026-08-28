<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

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
use PHPUnit\Framework\TestCase;

final class AuditEventDefinitionTest extends TestCase
{
    public function testSemanticTupleIsSevenFieldsAndDeliveryIsClosed(): void
    {
        $tuple = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        $event = new AuditEventDefinition(
            'security.test',
            1,
            1,
            AuditEventCategory::Security,
            ['security.test'],
            [$tuple],
            [],
            13,
            AuditDelivery::Durable,
        );
        self::assertSame('security.test', $event->toArray()['semanticTuples'][0]['actionType']);
        self::assertSame('durable', $event->delivery->value);
    }

    public function testDescriptorWireIsClosedAndAllowsMultipleTuplesPerAction(): void
    {
        $first = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        $second = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Failed,
            'resource',
            AuditOutcomeStatus::Failed,
            'failed',
            AuditPolicyDecision::Deny,
            'failed',
        );
        $safe = new AuditEvidenceFieldDefinition(
            'result_code',
            AuditEvidenceType::String,
            true,
            false,
            256,
            AuditEvidenceClassification::SafeCode,
        );
        $event = new AuditEventDefinition(
            'security.test',
            1,
            1,
            AuditEventCategory::Security,
            ['security.test'],
            [$second, $first],
            [$safe],
            8192,
            AuditDelivery::Required,
        );
        self::assertSame(
            [
                'name' => 'result_code',
                'type' => 'string',
                'required' => true,
                'nullable' => false,
                'maxUtf8Bytes' => 256,
                'classification' => 'safe_code',
            ],
            $event->toArray()['evidenceFields'][0],
        );
    }

    public function testDescriptorCouplingAndTupleUniquenessAreRejected(): void
    {
        $this->expectException(AuditValidationException::class);
        new AuditEvidenceFieldDefinition(
            'count',
            AuditEvidenceType::Integer,
            false,
            false,
            10,
            AuditEvidenceClassification::SafeCode,
        );
    }

    public function testVersionIsReservedEvidenceDescriptorName(): void
    {
        try {
            new AuditEvidenceFieldDefinition(
                'version',
                AuditEvidenceType::String,
                true,
                false,
                16,
                AuditEvidenceClassification::SafeCode,
            );
            self::fail('reserved evidence descriptor accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    public function testNormalizedDuplicateEvidenceFieldNamesAreRejected(): void
    {
        $field = new AuditEvidenceFieldDefinition(
            'result_code',
            AuditEvidenceType::String,
            false,
            false,
            16,
            AuditEvidenceClassification::SafeCode,
        );
        try {
            new AuditEventDefinition(
                'security.fields',
                1,
                1,
                AuditEventCategory::Security,
                ['security.fields'],
                [new AuditSemanticTuple(
                    'security.fields',
                    AuditActionPhase::Succeeded,
                    'resource',
                    AuditOutcomeStatus::Succeeded,
                    'ok',
                    AuditPolicyDecision::Allow,
                    'ok',
                )],
                [$field, $field],
                13,
                AuditDelivery::Required,
            );
            self::fail('duplicate normalized evidence name accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('order_or_uniqueness_invalid', $exception->code());
        }
    }

    public function testDuplicateFullSemanticTupleIsRejected(): void
    {
        $tuple = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        $this->expectException(AuditValidationException::class);
        new AuditEventDefinition(
            'security.test',
            1,
            1,
            AuditEventCategory::Security,
            ['security.test'],
            [$tuple, $tuple],
            [],
            13,
            AuditDelivery::Required,
        );
    }

    public function testEvidenceOrderAndUniquenessPrecedeTupleActionCoupling(): void
    {
        $tuple = new AuditSemanticTuple(
            'application.other',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        $first = new AuditEvidenceFieldDefinition(
            'alpha',
            AuditEvidenceType::String,
            false,
            false,
            16,
            AuditEvidenceClassification::SafeCode,
        );
        $last = new AuditEvidenceFieldDefinition(
            'zeta',
            AuditEvidenceType::String,
            false,
            false,
            16,
            AuditEvidenceClassification::SafeCode,
        );
        foreach ([[$last, $first], [$first, $first]] as $fields) {
            try {
                new AuditEventDefinition(
                    'application.event',
                    1,
                    1,
                    AuditEventCategory::Business,
                    ['application.event'],
                    [$tuple],
                    $fields,
                    13,
                    AuditDelivery::Required,
                );
                self::fail('stage-7 evidence defect was masked by tuple coupling');
            } catch (AuditValidationException $exception) {
                self::assertSame('order_or_uniqueness_invalid', $exception->code());
            }
        }
    }

    public function testAllClosedEnumsAreComplete(): void
    {
        self::assertSame(
            ['requested', 'started', 'committed', 'succeeded', 'denied', 'failed', 'unknown'],
            array_map(static fn(AuditActionPhase $phase): string => $phase->value, AuditActionPhase::cases()),
        );
        self::assertSame(
            ['business', 'security', 'access', 'configuration', 'control', 'retention', 'replay', 'rotation', 'outbox'],
            array_map(static fn(AuditEventCategory $category): string => $category->value, AuditEventCategory::cases()),
        );
        self::assertSame(
            ['succeeded', 'denied', 'failed', 'unknown'],
            array_map(static fn(AuditOutcomeStatus $status): string => $status->value, AuditOutcomeStatus::cases()),
        );
    }

    public function testInvalidBoundsAreRejected(): void
    {
        $this->expectException(AuditValidationException::class);
        new AuditEventDefinition(
            'security.test',
            1,
            1,
            AuditEventCategory::Security,
            [],
            [],
            [],
            0,
            AuditDelivery::Required,
        );
    }

    public function testDirectConstructorChecksMemberShapeBeforeCardinality(): void
    {
        try {
            new AuditEventDefinition(
                'security.test',
                1,
                1,
                AuditEventCategory::Security,
                [(object) []],
                [],
                [],
                13,
                AuditDelivery::Required,
            );
            self::fail('invalid member accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testSemanticTupleRunsAllStringStagesBeforeGrammar(): void
    {
        foreach ([
            ['Bad!', "resource\xFF",       'ok', 'ok', 'utf8_invalid'],
            ['Bad!', "resource\u{0001}",   'ok', 'ok', 'control_invalid'],
            ['Bad!', "e\u{0301}",          'ok', 'ok', 'nfc_invalid'],
            ['Bad!', str_repeat('x', 129), 'ok', 'ok', 'byte_bound_invalid'],
        ] as [$actionType, $resourceType, $outcomeCode, $policyReasonCode, $failure]) {
            try {
                new AuditSemanticTuple(
                    $actionType,
                    AuditActionPhase::Succeeded,
                    $resourceType,
                    AuditOutcomeStatus::Succeeded,
                    $outcomeCode,
                    AuditPolicyDecision::Allow,
                    $policyReasonCode,
                );
                self::fail('compound-invalid tuple accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($failure, $exception->code());
            }
        }
    }

    public function testGrammarPrecedesSemanticCardinality(): void
    {
        try {
            new AuditEventDefinition(
                'security.test',
                1,
                1,
                AuditEventCategory::Security,
                ['Bad'],
                [],
                [],
                8193,
                AuditDelivery::Required,
            );
            self::fail('compound-invalid definition accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    public function testCollectionBoundsAreInclusiveAndSingleDefectAtPlusOne(): void
    {
        $actions = [];
        $tuples = [];
        $fields = [];
        for ($index = 1; $index <= 32; ++$index) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $action = 'security.action.' . $suffix;
            $actions[] = $action;
            $tuples[] = new AuditSemanticTuple(
                $action,
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            );
            $fields[] = new AuditEvidenceFieldDefinition(
                'field_' . $suffix,
                AuditEvidenceType::String,
                false,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            );
        }
        $event = new AuditEventDefinition(
            'security.action_catalog',
            1,
            1,
            AuditEventCategory::Security,
            $actions,
            $tuples,
            $fields,
            8192,
            AuditDelivery::Required,
        );
        self::assertCount(32, $event->actionTypes);
        self::assertCount(32, $event->semanticTuples);
        self::assertCount(32, $event->evidenceFields);

        $extraAction = 'security.action.33';
        $extraTuple = new AuditSemanticTuple(
            $extraAction,
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        try {
            new AuditEventDefinition(
                'security.action_catalog',
                1,
                1,
                AuditEventCategory::Security,
                [...$actions, $extraAction],
                [...$tuples, $extraTuple],
                $fields,
                8192,
                AuditDelivery::Required,
            );
            self::fail('33 actions and tuples accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
        try {
            new AuditEventDefinition(
                'security.action_catalog',
                1,
                1,
                AuditEventCategory::Security,
                $actions,
                $tuples,
                [
                    ...$fields,
                    new AuditEvidenceFieldDefinition(
                        'field_33',
                        AuditEvidenceType::String,
                        false,
                        false,
                        256,
                        AuditEvidenceClassification::SafeCode,
                    ),
                ],
                8192,
                AuditDelivery::Required,
            );
            self::fail('33 evidence fields accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
        $tuple = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        foreach ([[], ['security.test']] as $actionTypes) {
            try {
                new AuditEventDefinition(
                    'security.test',
                    1,
                    1,
                    AuditEventCategory::Security,
                    $actionTypes,
                    $actionTypes === [] ? [$tuple] : [],
                    [],
                    8192,
                    AuditDelivery::Required,
                );
                self::fail('zero action or tuple count accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
    }

    public function testEvidenceByteBoundsAreInclusive(): void
    {
        $tuple = new AuditSemanticTuple(
            'security.test',
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        foreach ([1, 8192] as $maxEvidenceBytes)
            new AuditEventDefinition(
                'security.test',
                1,
                1,
                AuditEventCategory::Security,
                ['security.test'],
                [$tuple],
                [],
                $maxEvidenceBytes,
                AuditDelivery::Required,
            );
        foreach ([0, 8193] as $maxEvidenceBytes) {
            try {
                new AuditEventDefinition(
                    'security.test',
                    1,
                    1,
                    AuditEventCategory::Security,
                    ['security.test'],
                    [$tuple],
                    [],
                    $maxEvidenceBytes,
                    AuditDelivery::Required,
                );
                self::fail('invalid evidence byte bound accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
    }

    public function testDeclaredStringByteBoundsAreInclusive(): void
    {
        foreach ([1, 256] as $maxUtf8Bytes)
            new AuditEvidenceFieldDefinition(
                'result_code',
                AuditEvidenceType::String,
                true,
                false,
                $maxUtf8Bytes,
                AuditEvidenceClassification::SafeCode,
            );
        foreach ([0, 257] as $maxUtf8Bytes) {
            try {
                new AuditEvidenceFieldDefinition(
                    'result_code',
                    AuditEvidenceType::String,
                    true,
                    false,
                    $maxUtf8Bytes,
                    AuditEvidenceClassification::SafeCode,
                );
                self::fail('invalid declared string bound accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
    }
}
