<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
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
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use PHPUnit\Framework\TestCase;

final class AuditRecordFinalizerTest extends TestCase
{
    public function testFinalizerInjectsTrustedIdentityAndRecordedTime(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $final = $this->finalizer();
        $record = $final->finalize(
            $this->draft($catalog->catalogHash, $definition->delivery),
            new AuditRecordStamp('01941f29-7c00-7000-8000-000000000001', '2026-08-20T03:20:00.000000Z'),
        );
        $value = $record->toArray();

        self::assertSame('01941f29-7c00-7000-8000-000000000001', $value['recordId']);
        self::assertSame(
            [
                'occurredAt' => '2026-08-20T03:20:00.000000Z',
                'recordedAt' => '2026-08-20T03:20:00.000000Z',
            ],
            $value['time'],
        );
        self::assertArrayNotHasKey('integrity', $value);
    }

    public function testFinalizedSnapshotIsDetachedFromReturnedArrays(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $record = $this->finalizer()->finalize(
            $this->draft($catalog->catalogHash, $definition->delivery),
            new AuditRecordStamp('01941f29-7c00-7000-8000-000000000002', '2026-08-20T03:20:00.000000Z'),
        );
        $copy = $record->toArray();
        $copy['event']['type'] = 'changed.outside.record';
        $copy['time']['recordedAt'] = '2026-08-20T03:25:00.000000Z';

        self::assertSame($definition->type, $record->toArray()['event']['type']);
        self::assertSame('2026-08-20T03:20:00.000000Z', $record->toArray()['time']['recordedAt']);
    }

    public function testFinalizerSourceHasOneDraftReadAndOneLogicalValidationCall(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../src/Internal/Audit/Record/AuditRecordFinalizer.php');
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '->toArray()'));
        self::assertSame(1, substr_count($source, '->validateLogical('));
        self::assertStringNotContainsString('validateDraft(', $source);
    }

    public function testStampRejectsNonV7IdentityAndNonCanonicalTime(): void
    {
        foreach ([
            ['01941f29-7c00-4000-8000-000000000001',   '2026-08-20T03:20:00.000000Z'],
            ['01941F29-7C00-7000-8000-000000000001',   '2026-08-20T03:20:00.000000Z'],
            ['{01941f29-7c00-7000-8000-000000000001}', '2026-08-20T03:20:00.000000Z'],
            ['01941f29-7c00-7000-0000-000000000001',   '2026-08-20T03:20:00.000000Z'],
            ['01941f29-7c00-8000-8000-000000000001',   '2026-08-20T03:20:00.000000Z'],
            ['01941f29-7c00-7000-8000-00000000001',    '2026-08-20T03:20:00.000000Z'],
            ['01941f29-7c00-7000-8000-0000000000001',  '2026-08-20T03:20:00.000000Z'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-08-20T03:20:00Z'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-08-20T03:20:00.00000Z'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-08-20T03:20:00.1234567Z'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-08-20T03:20:00.000000z'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-08-20T03:20:00.000000+00:00'],
            ['01941f29-7c00-7000-8000-000000000001',   '2026-02-29T03:20:00.000000Z'],
        ] as [$recordId, $recordedAt]) {
            try {
                new AuditRecordStamp($recordId, $recordedAt);
                self::fail('invalid trusted stamp accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStampAcceptsCanonicalUuidV7AndSixDigitUtcTime(): void
    {
        $stamp = new AuditRecordStamp('01941f29-7c00-7000-8000-000000000001', '2026-08-20T03:20:00.123456Z');

        self::assertSame('01941f29-7c00-7000-8000-000000000001', $stamp->recordId);
        self::assertSame('2026-08-20T03:20:00.123456Z', $stamp->recordedAt);
    }

    private function finalizer(): AuditRecordFinalizer
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $codec = new AuditRecordCodec($validator, new AuditJcsCodec());
        return new AuditRecordFinalizer($validator, $codec);
    }

    private function draft(string $catalogHash, AuditDelivery $delivery): AuditRecordDraft
    {
        $definition = FrameworkAuditEventCatalog::catalog()->events[0];
        $tuple = $definition->semanticTuples[0];
        return new AuditRecordDraft(
            event: new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalogHash),
            action: new AuditRecordAction('action-requested', $tuple->actionType, $tuple->phase),
            actor: new AuditRecordActor(
                new AuditTypedReference('user', 'actor-1'),
                new AuditTypedReference('process', 'blackops-cli'),
            ),
            resource: new AuditRecordResource($tuple->resourceType, 'operation-1'),
            outcome: new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            policy: new AuditRecordPolicy(
                'operation-read',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                $delivery,
            ),
            tenant: null,
            occurredAt: '2026-08-20T03:20:00.000000Z',
            source: new AuditRecordSource(
                AuditSourceOwner::Framework,
                'operation-status',
                AuditSourceRuntime::Cli,
                'build-1',
            ),
            correlation: AuditRecordCorrelation::fromArray([
                'operationId' => null,
                'correlationId' => null,
                'causationId' => null,
                'attempt' => null,
                'traceId' => null,
                'spanId' => null,
                'sampled' => null,
            ]),
            evidence: new AuditRecordEvidence($definition->evidenceVersion),
        );
    }
}
