<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use PHPUnit\Framework\TestCase;

final class AuditRecordValidatorTest extends TestCase
{
    public function testDraftAndLogicalResolveTheSameCatalogDefinition(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $draft = $this->draft($catalog->catalogHash, $definition, $tuple);
        $validator->validateDraft($draft);
        $record = new TestLogicalRecord($this->logical($catalog->catalogHash, $definition, $tuple));
        $validated = $validator->validateLogical($record);
        self::assertSame(1, $record->calls);
        self::assertSame($definition->toArray(), $validated->definition->toArray());
        self::assertSame(14, count($validated->snapshot));
        self::assertSame(
            [
                'schemaVersion',
                'kind',
                'recordId',
                'event',
                'action',
                'actor',
                'resource',
                'outcome',
                'policy',
                'tenant',
                'time',
                'source',
                'correlation',
                'evidence',
            ],
            array_keys($validated->snapshot),
        );
    }

    public function testLogicalCaptureIsSingleAndDetachedFromLaterSourceMutation(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $record = new TestLogicalRecord($this->logical($catalog->catalogHash, $definition, $tuple));
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $validated = $validator->validateLogical($record);
        $record->value['recordId'] = 'replacement';
        $record->value['event']['type'] = 'replacement.event';
        self::assertSame('record-1', $validated->snapshot['recordId']);
        self::assertSame($definition->type, $validated->snapshot['event']['type']);
        self::assertSame(1, $record->calls);
    }

    public function testSecondReadWouldChangeButValidatorNeverReadsIt(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $record = new TestLogicalRecord($this->logical($catalog->catalogHash, $definition, $tuple), true);
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $validated = $validator->validateLogical($record);
        self::assertSame(1, $record->calls);
        self::assertSame('record-1', $validated->snapshot['recordId']);
    }

    public function testLogicalShapeRejectsRecursiveAndLargeUnknownSubtreesBeforeCopy(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));

        $recursive = $this->logical($catalog->catalogHash, $definition, $tuple);
        $recursive['unknown'] = &$recursive;
        $record = new TestLogicalRecord($recursive);
        try {
            $validator->validateLogical($record);
            self::fail('recursive unknown subtree accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
        self::assertSame(1, $record->calls);

        $large = $this->logical($catalog->catalogHash, $definition, $tuple);
        $large['actor']['execution']['unknown'] = array_fill(0, 4096, str_repeat('x', 256));
        $record = new TestLogicalRecord($large);
        try {
            $validator->validateLogical($record);
            self::fail('large nested unknown subtree accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
        self::assertSame(1, $record->calls);
    }

    public function testLogicalWidthGuardsRejectWideUnknownValuesWithoutLeakingThem(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));

        $wideTopLevel = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        for ($index = 0; $index < 4096; ++$index)
            $wideTopLevel['top-level-width-' . $index] = 'top-level-width-canary';

        $wideNested = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        for ($index = 0; $index < 4096; ++$index)
            $wideNested['actor']['execution']['nested-width-' . $index] = 'nested-width-canary';

        $wideCorrelation = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        for ($index = 0; $index < 4096; ++$index)
            $wideCorrelation['correlation']['correlation-width-' . $index] = 'correlation-width-canary';

        $wideEvidence = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $wideEvidence['evidence'] = ['version' => $definition->evidenceVersion];
        for ($index = 0; $index < 34; ++$index)
            $wideEvidence['evidence']['evidence-width-' . $index] = 'evidence-width-canary';

        $descriptorExternal = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $descriptorExternal['evidence']['descriptor-external-canary'] = 'ok';

        foreach ([
            [$wideTopLevel,       'top-level-width-canary'],
            [$wideNested,         'nested-width-canary'],
            [$wideCorrelation,    'correlation-width-canary'],
            [$wideEvidence,       'evidence-width-canary'],
            [$descriptorExternal, 'descriptor-external-canary'],
        ] as [$value, $canary]) {
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('wide or external value accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
                self::assertSame('shape_invalid', $exception->getMessage());
                self::assertStringNotContainsString($canary, $exception->getMessage());
            }
        }
    }

    public function testLogicalWidthGuardsPrecedeUntrustedEnumerationAndSnapshot(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../src/Internal/Audit/Record/AuditRecordValidator.php');
        self::assertIsString($source);

        $sameKeysStart = strpos($source, 'private function sameKeys');
        self::assertIsInt($sameKeysStart);
        $sameKeys = substr($source, $sameKeysStart);
        $sameKeysCount = strpos($sameKeys, 'if (count($value) !== count($keys))');
        $sameKeysEnumeration = strpos($sameKeys, 'array_keys($value)');
        self::assertIsInt($sameKeysCount);
        self::assertIsInt($sameKeysEnumeration);
        self::assertLessThan($sameKeysEnumeration, $sameKeysCount);

        $correlationStart = strpos($source, 'private function correlationShape');
        $evidenceStart = strpos($source, 'private function evidenceShape');
        self::assertIsInt($correlationStart);
        self::assertIsInt($evidenceStart);
        $correlation = substr($source, $correlationStart, $evidenceStart - $correlationStart);
        $correlationCount = strpos($correlation, 'count($value) > self::MAX_CORRELATION_MEMBERS');
        $correlationTraversal = strpos($correlation, 'foreach ($value as $name => $member)');
        self::assertIsInt($correlationCount);
        self::assertIsInt($correlationTraversal);
        self::assertLessThan($correlationTraversal, $correlationCount);
        self::assertStringNotContainsString('array_keys($value)', $correlation);

        $timeStart = strpos($source, 'private function timeShape');
        self::assertIsInt($timeStart);
        $evidence = substr($source, $evidenceStart, $timeStart - $evidenceStart);
        $evidenceCount = strpos($evidence, 'count($value) > self::MAX_EVIDENCE_MEMBERS');
        $evidenceTraversal = strpos($evidence, 'foreach ($value as $name => $member)');
        self::assertIsInt($evidenceCount);
        self::assertIsInt($evidenceTraversal);
        self::assertLessThan($evidenceTraversal, $evidenceCount);

        $logicalStart = strpos($source, 'public function validateLogical');
        $validateStart = strpos($source, 'private function validate(');
        self::assertIsInt($logicalStart);
        self::assertIsInt($validateStart);
        $logical = substr($source, $logicalStart, $validateStart - $logicalStart);
        $evidenceValidation = strpos($logical, '$this->validateEvidenceDefinitionShape');
        $snapshot = strpos($logical, '$snapshot = $this->snapshot');
        self::assertIsInt($evidenceValidation);
        self::assertIsInt($snapshot);
        self::assertLessThan($snapshot, $evidenceValidation);

        $snapshotStart = strpos($source, 'private function evidenceSnapshot');
        self::assertIsInt($snapshotStart);
        $snapshotBody = substr($source, $snapshotStart);
        self::assertStringContainsString('foreach ($definition->evidenceFields as $field)', $snapshotBody);
        self::assertStringNotContainsString('foreach ($value as $name => $member)', $snapshotBody);
    }

    public function testLogicalTimeAllowsExactlyFiveMinutesButRejectsOneMicrosecondMore(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $atCap = $this->logical($catalog->catalogHash, $definition, $tuple);
        $atCap['time'] = [
            'occurredAt' => '2026-08-20T03:25:00.000000Z',
            'recordedAt' => '2026-08-20T03:20:00.000000Z',
        ];
        $validator->validateLogical(new TestLogicalRecord($atCap));
        $over = $atCap;
        $over['time']['occurredAt'] = '2026-08-20T03:25:00.000001Z';
        try {
            $validator->validateLogical(new TestLogicalRecord($over));
            self::fail('logical time cap+1 accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }

        foreach (['-00:00', '+09:00'] as $offset) {
            $offsetValue = $this->logical($catalog->catalogHash, $definition, $tuple);
            $offsetValue['time']['occurredAt'] = '2026-08-20T03:20:00.000000' . $offset;
            try {
                $validator->validateLogical(new TestLogicalRecord($offsetValue));
                self::fail('non-UTC timestamp offset accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('grammar_invalid', $exception->code());
            }
        }
    }

    public function testEveryCatalogSemanticTupleMemberAndUnknownCatalogFailClosed(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        foreach ([
            ['event', 'category', 'business'],
            ['action', 'type', 'other.action'],
            ['action', 'phase', 'failed'],
            ['resource', 'type', 'other.resource'],
            ['outcome', 'status', 'failed'],
            ['outcome', 'code', 'other_code'],
            ['policy', 'decision', 'deny'],
            ['policy', 'reasonCode', 'other_reason'],
            ['policy', 'auditDelivery', AuditDelivery::Durable->value],
            ['source', 'owner', AuditSourceOwner::Application->value],
            ['source', 'runtime', 'unknown-runtime'],
        ] as [$section, $member, $replacement]) {
            $value = $this->logical($catalog->catalogHash, $definition, $tuple);
            $value[$section][$member] = $replacement;
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('semantic mismatch accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
        $unknown = $this->logical(str_repeat('b', 64), $definition, $tuple);
        try {
            $validator->validateLogical(new TestLogicalRecord($unknown));
            self::fail('unresolved catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testDefinitionDependentEvidenceUsesTheGlobalStageOrder(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $cases = [
            'unknown member before owner semantic' => [
                static function (array &$value): void {
                    $value['evidence']['unknown_field'] = 'safe_value';
                    $value['source']['owner'] = AuditSourceOwner::Framework->value;
                },
                'shape_invalid',
            ],
            'declared type before owner semantic' => [
                static function (array &$value): void {
                    $value['evidence']['result_code'] = 42;
                    $value['source']['owner'] = AuditSourceOwner::Framework->value;
                },
                'shape_invalid',
            ],
            'invalid utf8 before tuple semantic' => [
                static function (array &$value): void {
                    $value['evidence']['result_code'] = "ok\xFF";
                    $value['action']['phase'] = AuditActionPhase::Failed->value;
                },
                'utf8_invalid',
            ],
            'descriptor cap before delivery semantic' => [
                static function (array &$value): void {
                    $value['evidence']['opaque_ref'] = str_repeat('a', 72);
                    $value['policy']['auditDelivery'] = AuditDelivery::Durable->value;
                },
                'byte_bound_invalid',
            ],
            'classified grammar before category semantic' => [
                static function (array &$value): void {
                    $value['evidence']['result_code'] = 'bad!';
                    $value['event']['category'] = AuditEventCategory::Control->value;
                },
                'grammar_invalid',
            ],
            'evidence aggregate cap follows tuple semantic' => [
                static function (array &$value): void {
                    $value['evidence']['result_code'] = str_repeat('a', 40);
                    $value['action']['phase'] = AuditActionPhase::Failed->value;
                },
                'semantic_invalid',
            ],
        ];
        foreach ($cases as [$mutate, $expected]) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $mutate($value);
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('compound-invalid record was accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code());
            }
        }
    }

    public function testEvidencePresenceTypesAndClassificationsAreValidatedAgainstTheDefinition(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $valid = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $valid['evidence'] = [
            'version' => 1,
            'required_nullable' => 'ok',
            'attempt_count' => 7,
            'flag' => true,
            'opaque_ref' => 'oid:v1:' . str_repeat('a', 64),
            'result_code' => 'ok',
        ];
        $validator->validateLogical(new TestLogicalRecord($valid));

        $optional = $valid;
        $optional['evidence'] = ['version' => 1, 'required_nullable' => 'ok', 'result_code' => 'ok'];
        $validator->validateLogical(new TestLogicalRecord($optional));

        $nullable = $valid;
        $nullable['evidence']['attempt_count'] = null;
        $nullable['evidence']['flag'] = null;
        $nullable['evidence']['required_nullable'] = null;
        $validator->validateLogical(new TestLogicalRecord($nullable));

        $cases = [
            [
                'required field missing',
                static function (array &$value): void {
                    unset($value['evidence']['result_code']);
                },
                'semantic_invalid',
            ],
            [
                'required nullable field missing',
                static function (array &$value): void {
                    unset($value['evidence']['required_nullable']);
                },
                'semantic_invalid',
            ],
            [
                'non-nullable field null',
                static function (array &$value): void {
                    $value['evidence']['opaque_ref'] = null;
                },
                'semantic_invalid',
            ],
            [
                'string declared as integer',
                static function (array &$value): void {
                    $value['evidence']['attempt_count'] = '7';
                },
                'shape_invalid',
            ],
            [
                'integer declared as string',
                static function (array &$value): void {
                    $value['evidence']['result_code'] = 7;
                },
                'shape_invalid',
            ],
            [
                'boolean declared as integer',
                static function (array &$value): void {
                    $value['evidence']['flag'] = 1;
                },
                'shape_invalid',
            ],
            [
                'raw opaque token',
                static function (array &$value): void {
                    $value['evidence']['opaque_ref'] = 'raw-opaque-token';
                },
                'grammar_invalid',
            ],
            [
                'unknown member',
                static function (array &$value): void {
                    $value['evidence']['unknown_field'] = 'ok';
                },
                'shape_invalid',
            ],
            [
                'nested value',
                static function (array &$value): void {
                    $value['evidence']['result_code'] = ['ok'];
                },
                'shape_invalid',
            ],
            [
                'list value',
                static function (array &$value): void {
                    $value['evidence']['result_code'] = [0 => 'ok'];
                },
                'shape_invalid',
            ],
            [
                'object value',
                static function (array &$value): void {
                    $value['evidence']['result_code'] = ['value' => 'ok'];
                },
                'shape_invalid',
            ],
            [
                'float value',
                static function (array &$value): void {
                    $value['evidence']['result_code'] = 1.5;
                },
                'shape_invalid',
            ],
        ];
        foreach ($cases as [$name, $mutate, $expected]) {
            $value = $valid;
            $mutate($value);
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail($name . ' accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code(), $name);
            }
        }
    }

    public function testEvidenceTextAndGrammarBoundariesDoNotNormalizeInput(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $cases = [
            ['result_code', "e\xCC\x81",          'nfc_invalid'],
            ['result_code', "ok\x01",             'control_invalid'],
            ['result_code', "ok\u{200E}",         'control_invalid'],
            ['result_code', str_repeat('あ', 22), 'byte_bound_invalid'],
            ['result_code', ' ok',                'grammar_invalid'],
            ['result_code', 'OK',                 'grammar_invalid'],
            ['opaque_ref',  str_repeat('x', 71), 'grammar_invalid'],
        ];
        foreach ($cases as [$field, $evidence, $expected]) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $value['evidence'][$field] = $evidence;
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('unnormalized evidence accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code());
            }
        }
    }

    public function testLogicalRecordIdAndCorrelationBoundariesAreClosed(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        foreach ([str_repeat('a', 1), str_repeat('a', 256)] as $recordId) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $value['recordId'] = $recordId;
            $validator->validateLogical(new TestLogicalRecord($value));
        }
        $multibyteAtCap = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $multibyteAtCap['recordId'] = str_repeat('あ', 85);
        $validator->validateLogical(new TestLogicalRecord($multibyteAtCap));
        foreach ([str_repeat('a', 0), str_repeat('a', 257)] as $recordId) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $value['recordId'] = $recordId;
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('invalid record id accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('byte_bound_invalid', $exception->code());
            }
        }
        $multibyteOverCap = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $multibyteOverCap['recordId'] = str_repeat('あ', 86);
        try {
            $validator->validateLogical(new TestLogicalRecord($multibyteOverCap));
            self::fail('multibyte record id over cap accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('byte_bound_invalid', $exception->code());
        }
        $cases = [
            static function (array &$value): void {
                $value['correlation']['attempt']['number'] = 0;
            },
            static function (array &$value): void {
                $value['correlation']['attempt']['kind'] = 'unknown';
            },
            static function (array &$value): void {
                $value['correlation']['traceId'] = str_repeat('a', 31);
            },
            static function (array &$value): void {
                $value['correlation']['sampled'] = 'true';
            },
            static function (array &$value): void {
                $value['correlation']['unknown'] = null;
            },
        ];
        foreach ($cases as $mutate) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $mutate($value);
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('invalid correlation accepted');
            } catch (AuditValidationException $exception) {
                self::assertContains($exception->code(), [
                    'shape_invalid',
                    'grammar_invalid',
                    'byte_bound_invalid',
                    'semantic_invalid',
                ]);
            }
        }
    }

    public function testEvidenceFieldAndAggregateJcsCapsAreInclusive(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog(resultMax: 256);
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        $atFieldCap = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $atFieldCap['evidence'] = [
            'version' => 1,
            'required_nullable' => 'ok',
            'result_code' => str_repeat('a', 256),
        ];
        $validator->validateLogical(new TestLogicalRecord($atFieldCap));
        $overFieldCap = $atFieldCap;
        $overFieldCap['evidence']['result_code'] = str_repeat('a', 257);
        try {
            $validator->validateLogical(new TestLogicalRecord($overFieldCap));
            self::fail('field cap+1 accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('byte_bound_invalid', $exception->code());
        }

        $atAggregateCap = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $atAggregateCap['evidence'] = [
            'version' => 1,
            'required_nullable' => 'ok',
            'result_code' => str_repeat('a', 40),
        ];
        $aggregateLength = strlen(new AuditJcsCodec()->canonicalJson($atAggregateCap['evidence']));
        [$aggregateCatalog, $aggregateDefinition, $aggregateTuple] = $this->evidenceCatalog(
            resultMax: 256,
            maxEvidenceBytes: $aggregateLength,
        );
        $atAggregateCap = $this->logicalForDefinition(
            $aggregateCatalog->catalogHash,
            $aggregateDefinition,
            $aggregateTuple,
        );
        $atAggregateCap['evidence'] = [
            'version' => 1,
            'required_nullable' => 'ok',
            'result_code' => str_repeat('a', 40),
        ];
        $aggregateValidator =
            new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
                $aggregateCatalog,
            ]));
        $aggregateValidator->validateLogical(new TestLogicalRecord($atAggregateCap));
        $overAggregateCap = $atAggregateCap;
        $overAggregateCap['evidence']['result_code'] = str_repeat('a', 41);
        try {
            $aggregateValidator->validateLogical(new TestLogicalRecord($overAggregateCap));
            self::fail('aggregate cap+1 accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }

        $compound = $atAggregateCap;
        $compound['action']['phase'] = AuditActionPhase::Failed->value;
        $compound['evidence']['result_code'] = str_repeat('a', 41);
        try {
            $aggregateValidator->validateLogical(new TestLogicalRecord($compound));
            self::fail('tuple-invalid aggregate-cap compound accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testTwoCatalogHashesKeepTheSameSelectorBoundToItsOwnDefinition(): void
    {
        [$catalogA, $definitionA, $tuple] = $this->evidenceCatalog();
        [$catalogB, $definitionB] = $this->evidenceCatalog(
            catalogId: 'application.observed.other',
            evidenceName: 'other_code',
        );
        self::assertNotSame($catalogA->catalogHash, $catalogB->catalogHash);
        self::assertSame([$definitionA->type, $definitionA->version, $definitionA->evidenceVersion], [
            $definitionB->type,
            $definitionB->version,
            $definitionB->evidenceVersion,
        ]);
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalogA,
            $catalogB,
        ]));
        $a = $this->logicalForDefinition($catalogA->catalogHash, $definitionA, $tuple);
        $a['evidence'] = ['version' => 1, 'required_nullable' => 'ok', 'result_code' => 'ok'];
        $validator->validateLogical(new TestLogicalRecord($a));
        $b = $this->logicalForDefinition($catalogB->catalogHash, $definitionB, $tuple);
        $b['evidence'] = ['version' => 1, 'required_nullable' => 'ok', 'other_code' => 'ok'];
        $validator->validateLogical(new TestLogicalRecord($b));
        $substituted = $a;
        $substituted['event']['catalogHash'] = $catalogB->catalogHash;
        try {
            $validator->validateLogical(new TestLogicalRecord($substituted));
            self::fail('definition substitution accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testEventVersionAndEvidenceSelectorsFailIndependently(): void
    {
        [$catalog, $definition, $tuple] = $this->evidenceCatalog();
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog,
        ]));
        foreach ([
            static function (array &$value): void {
                $value['event']['type'] = 'business.other.event';
            },
            static function (array &$value): void {
                $value['event']['version'] = 2;
            },
            static function (array &$value): void {
                $value['evidence']['version'] = 2;
            },
            static function (array &$value): void {
                $value['event']['catalogHash'] = str_repeat('b', 64);
            },
        ] as $mutate) {
            $value = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
            $mutate($value);
            try {
                $validator->validateLogical(new TestLogicalRecord($value));
                self::fail('independent selector failure accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }

        $grammarInvalid = $this->logicalForDefinition($catalog->catalogHash, $definition, $tuple);
        $grammarInvalid['event']['catalogHash'] = str_repeat('A', 64);
        try {
            $validator->validateLogical(new TestLogicalRecord($grammarInvalid));
            self::fail('uppercase catalog hash accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    /**
     * @return array{0:AuditEventCatalog,1:AuditEventDefinition,2:AuditSemanticTuple}
     */
    private function evidenceCatalog(
        string $catalogId = 'application.observed',
        string $evidenceName = 'result_code',
        int $resultMax = 64,
        int $maxEvidenceBytes = 1024,
    ): array {
        $tuple = new AuditSemanticTuple(
            'business.order.created',
            AuditActionPhase::Succeeded,
            'order',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'allowed',
        );
        $fields = [
            'attempt_count' => new AuditEvidenceFieldDefinition(
                'attempt_count',
                AuditEvidenceType::Integer,
                false,
                true,
            ),
            'flag' => new AuditEvidenceFieldDefinition('flag', AuditEvidenceType::Boolean, false, true),
            'opaque_ref' => new AuditEvidenceFieldDefinition(
                'opaque_ref',
                AuditEvidenceType::String,
                false,
                false,
                71,
                AuditEvidenceClassification::OpaqueId,
            ),
            'required_nullable' => new AuditEvidenceFieldDefinition(
                'required_nullable',
                AuditEvidenceType::String,
                true,
                true,
                $resultMax,
                AuditEvidenceClassification::SafeCode,
            ),
            $evidenceName => new AuditEvidenceFieldDefinition(
                $evidenceName,
                AuditEvidenceType::String,
                true,
                false,
                $resultMax,
                AuditEvidenceClassification::SafeCode,
            ),
        ];
        ksort($fields);
        $definition = new AuditEventDefinition(
            'business.order.created',
            1,
            1,
            AuditEventCategory::Business,
            ['business.order.created'],
            [$tuple],
            array_values($fields),
            $maxEvidenceBytes,
            AuditDelivery::Required,
        );
        $catalog = new AuditEventCatalogCodec()->catalog($catalogId, 1, AuditSourceOwner::Application, [$definition]);
        return [$catalog, $definition, $tuple];
    }

    /** @return array<string,mixed> */
    private function logicalForDefinition(
        string $hash,
        AuditEventDefinition $definition,
        AuditSemanticTuple $tuple,
    ): array {
        $value = $this->logical($hash, $definition, $tuple);
        $value['source']['owner'] = AuditSourceOwner::Application->value;
        $value['evidence'] = ['version' => $definition->evidenceVersion];
        foreach ($definition->evidenceFields as $field) {
            if (!$field->required)
                continue;
            $value['evidence'][$field->name] = match ($field->type) {
                AuditEvidenceType::String => $field->classification === AuditEvidenceClassification::OpaqueId
                    ? 'oid:v1:' . str_repeat('a', 64)
                    : 'ok',
                AuditEvidenceType::Integer => 1,
                AuditEvidenceType::Boolean => true,
            };
        }
        return $value;
    }

    public function testValidatedResultIsNotTheLogicalInterface(): void
    {
        self::assertFalse(is_a(
            \BlackOps\Internal\Audit\Record\ValidatedAuditLogicalRecord::class,
            \BlackOps\Internal\Audit\Record\AuditLogicalRecord::class,
            true,
        ));
        $constructor = new \ReflectionClass(\BlackOps\Internal\Audit\Record\ValidatedAuditLogicalRecord::class)->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(
            ['snapshot', 'definition'],
            array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $constructor->getParameters(),
            ),
        );
    }

    /** @return array<string,mixed> */
    private function logical(
        string $hash,
        AuditEventDefinition $definition,
        \BlackOps\Core\Audit\AuditSemanticTuple $tuple,
    ): array {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit',
            'recordId' => 'record-1',
            'event' => [
                'type' => $definition->type,
                'version' => $definition->version,
                'category' => $definition->category->value,
                'catalogHash' => $hash,
            ],
            'action' => ['id' => 'action-1', 'type' => $tuple->actionType, 'phase' => $tuple->phase->value],
            'actor' => [
                'principal' => ['type' => 'user', 'id' => 'user-1'],
                'execution' => ['type' => 'cli', 'id' => 'exec-1'],
            ],
            'resource' => ['type' => $tuple->resourceType, 'id' => 'resource-1'],
            'outcome' => ['status' => $tuple->outcomeStatus->value, 'code' => $tuple->outcomeCode],
            'policy' => [
                'id' => 'policy-1',
                'version' => 1,
                'decision' => $tuple->policyDecision->value,
                'reasonCode' => $tuple->policyReasonCode,
                'auditDelivery' => $definition->delivery->value,
            ],
            'tenant' => ['type' => 'tenant', 'id' => 'tenant-1'],
            'time' => [
                'occurredAt' => '2026-08-20T03:20:00.000000Z',
                'recordedAt' => '2026-08-20T03:20:00.000000Z',
            ],
            'source' => [
                'owner' => AuditSourceOwner::Framework->value,
                'component' => 'component-1',
                'runtime' => AuditSourceRuntime::Cli->value,
                'buildId' => 'build-1',
            ],
            'correlation' => [
                'operationId' => 'operation-1',
                'correlationId' => 'correlation-1',
                'causationId' => 'causation-1',
                'attempt' => ['number' => 1, 'kind' => 'initial'],
                'traceId' => str_repeat('a', 32),
                'spanId' => str_repeat('b', 16),
                'sampled' => true,
            ],
            'evidence' => ['version' => $definition->evidenceVersion],
        ];
    }

    private function draft(
        string $hash,
        AuditEventDefinition $definition,
        \BlackOps\Core\Audit\AuditSemanticTuple $tuple,
    ): AuditRecordDraft {
        return new AuditRecordDraft(
            event: new AuditRecordEvent($definition->type, $definition->version, $definition->category, $hash),
            action: new AuditRecordAction('action-1', $tuple->actionType, $tuple->phase),
            actor: new AuditRecordActor(
                new AuditTypedReference('user', 'user-1'),
                new AuditTypedReference('cli', 'exec-1'),
            ),
            resource: new AuditRecordResource($tuple->resourceType, 'resource-1'),
            outcome: new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            policy: new AuditRecordPolicy(
                'policy-1',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                $definition->delivery,
            ),
            tenant: new AuditTypedReference('tenant', 'tenant-1'),
            occurredAt: '2026-08-20T03:20:00.000000Z',
            source: new AuditRecordSource(
                AuditSourceOwner::Framework,
                'component-1',
                AuditSourceRuntime::Cli,
                'build-1',
            ),
            correlation: AuditRecordCorrelation::fromArray([
                'operationId' => 'operation-1',
                'correlationId' => 'correlation-1',
                'causationId' => 'causation-1',
                'attempt' => ['number' => 1, 'kind' => 'initial'],
                'traceId' => str_repeat('a', 32),
                'spanId' => str_repeat('b', 16),
                'sampled' => true,
            ]),
            evidence: new AuditRecordEvidence($definition->evidenceVersion),
        );
    }
}

/** @internal Test-only logical record fixture. */
final class TestLogicalRecord implements \BlackOps\Internal\Audit\Record\AuditLogicalRecord
{
    /** @param array<string,mixed> $value */
    public function __construct(
        public array $value,
        private bool $changeOnSecondRead = false,
    ) {}

    public int $calls = 0;

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        ++$this->calls;
        if ($this->changeOnSecondRead && $this->calls > 1)
            $this->value['recordId'] = 'changed-on-second-read';
        return $this->value;
    }
}
