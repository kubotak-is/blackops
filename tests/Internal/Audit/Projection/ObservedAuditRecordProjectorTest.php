<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Projection;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditProjectionException;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Projection\ObservedAuditRecordProjector;
use BlackOps\Internal\Audit\Record\AuditLogicalRecord;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use PHPUnit\Framework\TestCase;

final class ObservedAuditRecordProjectorTest extends TestCase
{
    public function testProjectionIsExactClosedLossyWireWithPurposeBoundReferences(): void
    {
        [$catalog, $definition] = $this->catalog();
        $projector = $this->projector($catalog);
        $bytes = $projector->project(new ProjectorLogicalRecord($this->logical($catalog->catalogHash, $definition)));
        self::assertStringEndsWith("\n", $bytes);
        self::assertSame(1, substr_count($bytes, "\n"));
        $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        $actualKeys = array_keys($decoded);
        $expectedKeys = [
            'schemaVersion',
            'kind',
            'projection',
            'recordRef',
            'event',
            'action',
            'actor',
            'resource',
            'outcome',
            'policy',
            'tenantRef',
            'time',
            'source',
            'correlation',
            'evidence',
        ];
        sort($actualKeys);
        sort($expectedKeys);
        self::assertSame($expectedKeys, $actualKeys);
        self::assertSame('observed_v1', $decoded['projection']);
        $actualCorrelationKeys = array_keys($decoded['correlation']);
        $expectedCorrelationKeys = [
            'operationRef',
            'correlationRef',
            'causationRef',
            'attempt',
            'traceRef',
            'spanRef',
            'sampled',
        ];
        sort($actualCorrelationKeys);
        sort($expectedCorrelationKeys);
        self::assertSame($expectedCorrelationKeys, $actualCorrelationKeys);
        $actualEvidenceKeys = array_keys($decoded['evidence']);
        sort($actualEvidenceKeys);
        self::assertSame(['opaque_ref', 'result_code', 'version'], $actualEvidenceKeys);
        self::assertSame('ok', $decoded['evidence']['result_code']);
        self::assertSame('oid:v1:' . str_repeat('a', 64), $decoded['evidence']['opaque_ref']);
        foreach (['missing_canary', 'null_canary', 'integer_canary', 'boolean_canary'] as $field) {
            self::assertArrayNotHasKey($field, $decoded['evidence']);
        }
        foreach ([
            'record-id-canary',
            'action-id-canary',
            'principal-id-canary',
            'principal-type-canary',
            'execution-id-canary',
            'execution-type-canary',
            'resource-id-canary',
            'tenant-id-canary',
            'tenant-type-canary',
            'operation-id-canary',
            'correlation-id-canary',
            'causation-id-canary',
            '0123456789abcdef0123456789abcdef',
            'fedcba9876543210',
            'policy-id-canary',
            'component-canary',
            'build-id-canary',
        ] as $canary) {
            self::assertStringNotContainsString($canary, $bytes);
        }
        foreach (['missing_canary', 'null_canary', 'integer_canary', 'boolean_canary'] as $field) {
            self::assertStringNotContainsString($field, $bytes);
        }
    }

    public function testPhpProjectionMatchesTheJsonlGoldenFixtureExactly(): void
    {
        [$catalog, $definition] = $this->catalog();
        $fixture = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/observed-audit-record-v1.jsonl');
        self::assertIsString($fixture);
        $row = json_decode(trim($fixture), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($row);
        self::assertIsArray($row['logical'] ?? null);
        self::assertIsString($row['expected'] ?? null);
        /** @var array<string,mixed> $logical */
        $logical = $row['logical'];
        self::assertSame($catalog->catalogHash, $logical['event']['catalogHash'] ?? null);
        self::assertSame($row['expected'], $this->projector($catalog)->project(new ProjectorLogicalRecord($logical)));
        self::assertSame($definition->type, $logical['event']['type'] ?? null);
    }

    public function testEveryOpaqueReferenceUsesItsPurposeAndTenantUsesTypedContext(): void
    {
        [$catalog, $definition] = $this->catalog();
        $logical = $this->logical($catalog->catalogHash, $definition);
        $decoded = json_decode(
            $this->projector($catalog)->project(new ProjectorLogicalRecord($logical)),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );
        $deriver = $this->deriver();
        foreach ([
            ['recordRef', AuditOpaqueIdPurpose::Record, $logical['recordId']],
            ['action.ref', AuditOpaqueIdPurpose::Action, $logical['action']['id']],
            ['actor.principalRef', AuditOpaqueIdPurpose::Principal, $logical['actor']['principal']['id']],
            ['actor.executionRef', AuditOpaqueIdPurpose::Execution, $logical['actor']['execution']['id']],
            ['resource.ref', AuditOpaqueIdPurpose::Resource, $logical['resource']['id']],
            ['correlation.operationRef', AuditOpaqueIdPurpose::Operation, $logical['correlation']['operationId']],
            ['correlation.correlationRef', AuditOpaqueIdPurpose::Correlation, $logical['correlation']['correlationId']],
            ['correlation.causationRef', AuditOpaqueIdPurpose::Causation, $logical['correlation']['causationId']],
            ['correlation.traceRef', AuditOpaqueIdPurpose::Trace, $logical['correlation']['traceId']],
            ['correlation.spanRef', AuditOpaqueIdPurpose::Span, $logical['correlation']['spanId']],
        ] as [$path, $purpose, $context]) {
            $actual = match ($path) {
                'recordRef' => $decoded['recordRef'],
                'action.ref' => $decoded['action']['ref'],
                'actor.principalRef' => $decoded['actor']['principalRef'],
                'actor.executionRef' => $decoded['actor']['executionRef'],
                'resource.ref' => $decoded['resource']['ref'],
                'correlation.operationRef' => $decoded['correlation']['operationRef'],
                'correlation.correlationRef' => $decoded['correlation']['correlationRef'],
                'correlation.causationRef' => $decoded['correlation']['causationRef'],
                'correlation.traceRef' => $decoded['correlation']['traceRef'],
                'correlation.spanRef' => $decoded['correlation']['spanRef'],
            };
            self::assertSame($deriver->deriveScalar($purpose, $context)->value, $actual, $path);
        }
        self::assertSame($deriver->deriveTenant($logical['tenant'])->value, $decoded['tenantRef']);
        self::assertNotSame(
            $decoded['recordRef'],
            $deriver->deriveScalar(AuditOpaqueIdPurpose::Action, $logical['recordId'])->value,
        );
        self::assertNotSame(
            $decoded['tenantRef'],
            $deriver->deriveTenant(['type' => 'other-tenant', 'id' => $logical['tenant']['id']])->value,
        );
        try {
            $deriver->deriveTenant($logical['tenant']['id']);
            self::fail('scalar tenant context accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
        $otherKey = new HmacAuditOpaqueIdDeriver(str_repeat("\xFF", 32));
        $otherProjector = new ObservedAuditRecordProjector(
            new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog])),
            $otherKey,
        );
        self::assertNotSame(
            $this->projector($catalog)->project(new ProjectorLogicalRecord($logical)),
            $otherProjector->project(new ProjectorLogicalRecord($logical)),
        );
        $otherContext = $logical;
        $otherContext['recordId'] = 'different-record-context';
        self::assertNotSame(
            $this->projector($catalog)->project(new ProjectorLogicalRecord($logical)),
            $this->projector($catalog)->project(new ProjectorLogicalRecord($otherContext)),
        );
    }

    public function testNullAndOmittedCorrelationAndActorTenantConvergeToNull(): void
    {
        [$catalog, $definition] = $this->catalog();
        $omitted = $this->logical($catalog->catalogHash, $definition);
        $omitted['actor']['principal'] = null;
        $omitted['tenant'] = null;
        $omitted['correlation'] = [];
        $explicitNull = $omitted;
        $explicitNull['correlation'] = [
            'operationId' => null,
            'correlationId' => null,
            'causationId' => null,
            'attempt' => null,
            'traceId' => null,
            'spanId' => null,
            'sampled' => null,
        ];
        $mixed = $omitted;
        $mixed['correlation'] = [
            'operationId' => null,
            'traceId' => null,
            'sampled' => null,
        ];
        $omittedBytes = $this->projector($catalog)->project(new ProjectorLogicalRecord($omitted));
        $explicitNullBytes = $this->projector($catalog)->project(new ProjectorLogicalRecord($explicitNull));
        $mixedBytes = $this->projector($catalog)->project(new ProjectorLogicalRecord($mixed));
        self::assertStringEndsWith("\n", $omittedBytes);
        self::assertSame($omittedBytes, $explicitNullBytes);
        self::assertSame($omittedBytes, $mixedBytes);
        $decoded = json_decode($explicitNullBytes, true, 32, JSON_THROW_ON_ERROR);
        self::assertNull($decoded['actor']['principalRef']);
        self::assertNull($decoded['tenantRef']);
        $expectedCorrelation = [
            'operationRef' => null,
            'correlationRef' => null,
            'causationRef' => null,
            'attempt' => null,
            'traceRef' => null,
            'spanRef' => null,
            'sampled' => null,
        ];
        ksort($expectedCorrelation);
        ksort($decoded['correlation']);
        self::assertSame($expectedCorrelation, $decoded['correlation']);
    }

    public function testProjectorCallsValidatorOnceAndDoesNotReadLogicalAgain(): void
    {
        [$catalog, $definition] = $this->catalog();
        $record = new ProjectorLogicalRecord($this->logical($catalog->catalogHash, $definition), true);
        $this->projector($catalog)->project($record);
        self::assertSame(1, $record->calls);
    }

    public function testRecursiveAndDeepUnknownInputsBecomeValueFreeProjectionFailures(): void
    {
        [$catalog, $definition] = $this->catalog();
        $recursive = $this->logical($catalog->catalogHash, $definition);
        $recursive['unknown'] = &$recursive;
        $large = $this->logical($catalog->catalogHash, $definition);
        $large['actor']['execution']['unknown'] = array_fill(0, 4096, 'projection-deep-canary');
        $wideTopLevel = $this->logical($catalog->catalogHash, $definition);
        for ($index = 0; $index < 4096; ++$index)
            $wideTopLevel['projection-top-level-' . $index] = 'projection-width-canary';
        $wideNested = $this->logical($catalog->catalogHash, $definition);
        for ($index = 0; $index < 4096; ++$index)
            $wideNested['actor']['execution']['projection-nested-' . $index] = 'projection-width-canary';
        $wideCorrelation = $this->logical($catalog->catalogHash, $definition);
        for ($index = 0; $index < 4096; ++$index)
            $wideCorrelation['correlation']['projection-correlation-' . $index] = 'projection-width-canary';
        $wideEvidence = $this->logical($catalog->catalogHash, $definition);
        for ($index = 0; $index < 34; ++$index)
            $wideEvidence['evidence']['projection-evidence-' . $index] = 'projection-width-canary';
        $descriptorExternal = $this->logical($catalog->catalogHash, $definition);
        $descriptorExternal['evidence']['projection-descriptor-external'] = 'projection-width-canary';

        foreach ([
            $recursive,
            $large,
            $wideTopLevel,
            $wideNested,
            $wideCorrelation,
            $wideEvidence,
            $descriptorExternal,
        ] as $value) {
            try {
                $this->projector($catalog)->project(new ProjectorLogicalRecord($value));
                self::fail('unknown recursive/deep input accepted');
            } catch (AuditProjectionException $exception) {
                self::assertSame('projection_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
                self::assertSame([], $exception->__debugInfo());
                self::assertStringNotContainsString('projection-deep-canary', serialize($exception));
                foreach (['projection-deep-canary', 'projection-width-canary'] as $canary) {
                    self::assertStringNotContainsString($canary, serialize($exception));
                    self::assertStringNotContainsString($canary, json_encode($exception, JSON_THROW_ON_ERROR));
                }
            }
        }
    }

    public function testEveryProjectionFailureIsFixedAndValueFree(): void
    {
        [$catalog, $definition] = $this->catalog();
        $value = $this->logical($catalog->catalogHash, $definition);
        $value['resource']['id'] = 'secret-rejected-resource';
        $value['evidence']['result_code'] = 'bad!';
        try {
            $this->projector($catalog)->project(new ProjectorLogicalRecord($value));
            self::fail('invalid projection accepted');
        } catch (AuditProjectionException $exception) {
            self::assertSame('projection_invalid', $exception->getMessage());
            self::assertSame('projection_invalid', $exception->code());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('secret-rejected-resource', serialize($exception));
            self::assertSame([], $exception->__debugInfo());
        }
    }

    public function testFailureCanariesNeverEscapeAnyExceptionRepresentation(): void
    {
        [$catalog, $definition] = $this->catalog();
        $value = $this->logical($catalog->catalogHash, $definition);
        $canaries = [
            'record-failure-canary',
            'action-failure-canary',
            'principal-failure-canary',
            'execution-failure-canary',
            'resource-failure-canary',
            'tenant-failure-canary',
            'operation-failure-canary',
            'correlation-failure-canary',
            'causation-failure-canary',
            '0123456789abcdef0123456789abcdef',
            'fedcba9876543210',
            'policy-failure-canary',
            'component-failure-canary',
            'build-failure-canary',
            'evidence-failure-canary',
            'principal-type-failure-canary',
            'execution-type-failure-canary',
            'tenant-type-failure-canary',
            'integer_canary',
            'boolean_canary',
            'null_canary',
            '9007199254740991',
        ];
        $value['recordId'] = $canaries[0];
        $value['action']['id'] = $canaries[1];
        $value['actor']['principal']['id'] = $canaries[2];
        $value['actor']['execution']['id'] = $canaries[3];
        $value['resource']['id'] = $canaries[4];
        $value['tenant']['id'] = $canaries[5];
        $value['correlation']['operationId'] = $canaries[6];
        $value['correlation']['correlationId'] = $canaries[7];
        $value['correlation']['causationId'] = $canaries[8];
        $value['correlation']['traceId'] = $canaries[9];
        $value['correlation']['spanId'] = $canaries[10];
        $value['policy']['id'] = $canaries[11];
        $value['source']['component'] = $canaries[12];
        $value['source']['buildId'] = $canaries[13];
        $value['evidence']['unknown_failure_canary'] = $canaries[14];
        $value['actor']['principal']['type'] = $canaries[15];
        $value['actor']['execution']['type'] = $canaries[16];
        $value['tenant']['type'] = $canaries[17];
        $value['evidence']['integer_canary'] = 9007199254740991;
        $value['evidence']['boolean_canary'] = false;
        $value['evidence']['null_canary'] = null;
        try {
            $this->projector($catalog)->project(new ProjectorLogicalRecord($value));
            self::fail('canary-bearing invalid record accepted');
        } catch (AuditProjectionException $exception) {
            $representations = [
                $exception->getMessage(),
                serialize($exception),
                var_export($exception, true),
                json_encode($exception, JSON_THROW_ON_ERROR),
            ];
            foreach ($representations as $representation)
                foreach ($canaries as $canary)
                    self::assertStringNotContainsString($canary, $representation);
            self::assertNull($exception->getPrevious());
            self::assertSame([], $exception->__debugInfo());
        }
    }

    public function testJcsFailureReturnsNoPartialOutput(): void
    {
        [$catalog, $definition] = $this->catalog();
        $jcs = new class extends AuditJcsCodec {
            public function canonicalJson(mixed $value): string
            {
                throw new \RuntimeException('source should not escape');
            }
        };
        $projector = new ObservedAuditRecordProjector(
            new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog])),
            $this->deriver(),
            $jcs,
        );
        $this->expectException(AuditProjectionException::class);
        $projector->project(new ProjectorLogicalRecord($this->logical($catalog->catalogHash, $definition)));
    }

    public function testValidatedWrapperAndUntrustedArraysAreNotProjectorInputs(): void
    {
        [$catalog, $definition] = $this->catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $validated = $validator->validateLogical(new ProjectorLogicalRecord($this->logical(
            $catalog->catalogHash,
            $definition,
        )));
        $projector = new ObservedAuditRecordProjector($validator, $this->deriver());
        try {
            /** @phpstan-ignore-next-line */
            $projector->project($validated);
            self::fail('validated wrapper accepted by projector');
        } catch (\TypeError) {
            self::addToAssertionCount(1);
        }
        try {
            /** @phpstan-ignore-next-line */
            $projector->project($validated->snapshot);
            self::fail('raw array accepted by projector');
        } catch (\TypeError) {
            self::addToAssertionCount(1);
        }
    }

    /** @return array{0:\BlackOps\Core\Audit\AuditEventCatalog,1:AuditEventDefinition} */
    private function catalog(): array
    {
        $tuple = new \BlackOps\Core\Audit\AuditSemanticTuple(
            'business.order.created',
            AuditActionPhase::Succeeded,
            'order',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'allowed',
        );
        $definition = new AuditEventDefinition(
            'business.order.created',
            1,
            1,
            AuditEventCategory::Business,
            ['business.order.created'],
            [$tuple],
            [
                new AuditEvidenceFieldDefinition('boolean_canary', AuditEvidenceType::Boolean, false, true),
                new AuditEvidenceFieldDefinition('integer_canary', AuditEvidenceType::Integer, false, true),
                new AuditEvidenceFieldDefinition(
                    'missing_canary',
                    AuditEvidenceType::String,
                    false,
                    false,
                    64,
                    AuditEvidenceClassification::SafeCode,
                ),
                new AuditEvidenceFieldDefinition(
                    'null_canary',
                    AuditEvidenceType::String,
                    false,
                    true,
                    64,
                    AuditEvidenceClassification::SafeCode,
                ),
                new AuditEvidenceFieldDefinition(
                    'opaque_ref',
                    AuditEvidenceType::String,
                    false,
                    false,
                    71,
                    AuditEvidenceClassification::OpaqueId,
                ),
                new AuditEvidenceFieldDefinition(
                    'result_code',
                    AuditEvidenceType::String,
                    true,
                    false,
                    64,
                    AuditEvidenceClassification::SafeCode,
                ),
            ],
            1024,
            AuditDelivery::Required,
        );
        $catalog = new AuditEventCatalogCodec()->catalog(
            'application.observed',
            1,
            AuditSourceOwner::Application,
            [$definition],
        );
        return [$catalog, $definition];
    }

    private function projector(\BlackOps\Core\Audit\AuditEventCatalog $catalog): ObservedAuditRecordProjector
    {
        return new ObservedAuditRecordProjector(
            new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog])),
            $this->deriver(),
        );
    }

    private function deriver(): HmacAuditOpaqueIdDeriver
    {
        return new HmacAuditOpaqueIdDeriver(implode('', array_map(static fn(int $i): string => chr($i), range(0, 31))));
    }

    /** @return array<string,mixed> */
    private function logical(string $hash, AuditEventDefinition $definition): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit',
            'recordId' => 'record-id-canary',
            'event' => [
                'type' => $definition->type,
                'version' => $definition->version,
                'category' => $definition->category->value,
                'catalogHash' => $hash,
            ],
            'action' => ['id' => 'action-id-canary', 'type' => 'business.order.created', 'phase' => 'succeeded'],
            'actor' => [
                'principal' => ['type' => 'principal-type-canary', 'id' => 'principal-id-canary'],
                'execution' => ['type' => 'execution-type-canary', 'id' => 'execution-id-canary'],
            ],
            'resource' => ['type' => 'order', 'id' => 'resource-id-canary'],
            'outcome' => ['status' => 'succeeded', 'code' => 'ok'],
            'policy' => [
                'id' => 'policy-id-canary',
                'version' => 1,
                'decision' => 'allow',
                'reasonCode' => 'allowed',
                'auditDelivery' => 'required',
            ],
            'tenant' => ['type' => 'tenant-type-canary', 'id' => 'tenant-id-canary'],
            'time' => [
                'occurredAt' => '2026-08-20T03:20:00.000000Z',
                'recordedAt' => '2026-08-20T03:20:00.000000Z',
            ],
            'source' => [
                'owner' => 'application',
                'component' => 'component-canary',
                'runtime' => 'cli',
                'buildId' => 'build-id-canary',
            ],
            'correlation' => [
                'operationId' => 'operation-id-canary',
                'correlationId' => 'correlation-id-canary',
                'causationId' => 'causation-id-canary',
                'attempt' => ['number' => 2, 'kind' => 'retry'],
                'traceId' => '0123456789abcdef0123456789abcdef',
                'spanId' => 'fedcba9876543210',
                'sampled' => true,
            ],
            'evidence' => [
                'version' => 1,
                'result_code' => 'ok',
                'opaque_ref' => 'oid:v1:' . str_repeat('a', 64),
                'integer_canary' => 9007199254740991,
                'boolean_canary' => false,
                'null_canary' => null,
            ],
        ];
    }
}

final class ProjectorLogicalRecord implements AuditLogicalRecord
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
            $this->value['recordId'] = 'second-read-record';
        return $this->value;
    }
}
