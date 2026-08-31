<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Storage;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditRecordIntegrity;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditLookupResult;
use BlackOps\Internal\Audit\Storage\AuditSegmentCloseResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use PHPUnit\Framework\TestCase;

final class AuditStoreValueTypesTest extends TestCase
{
    public function testFailureEnumAndExceptionExposeOnlySafeCode(): void
    {
        self::assertSame(
            [
                'resource_unavailable',
                'idempotency_conflict',
                'segment_conflict',
                'storage_unavailable',
                'integrity_invalid',
            ],
            array_map(static fn(AuditStoreFailure $failure): string => $failure->value, AuditStoreFailure::cases()),
        );

        $exception = new AuditStoreException(AuditStoreFailure::StorageUnavailable);

        self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
        self::assertSame('storage_unavailable', $exception->code());
        self::assertSame('Audit store operation failed.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertStringNotContainsString('blackops', var_export($exception, true));
    }

    public function testAppendAndLookupResultsAreDetachedAndRejectSerialization(): void
    {
        $record = $this->storedRecord();
        $append = new AuditAppendResult($record, true);

        self::assertSame($record, $append->record());
        self::assertTrue($append->inserted());
        self::assertSame([], $append->__debugInfo());
        self::assertSame([], $append->__serialize());
        self::assertFalse(is_subclass_of($append, \JsonSerializable::class));
        self::assertFalse($append instanceof \Stringable);
        self::assertFalse(method_exists($append, '__set_state'));
        $this->assertSerializationRejected($append);

        $complete = new CompleteAuditRecord(
            ['kind' => 'audit_logical_record'],
            $this->definition(),
            new AuditRecordIntegrity(
                'audit-sha256-jcs-v1',
                'system:global',
                '018f0a00-0000-7000-8000-000000000002',
                1,
                null,
                str_repeat('a', 64),
                str_repeat('b', 64),
            ),
            'canonical-bytes',
        );
        $found = AuditLookupResult::fromRecord($complete);
        $notFound = AuditLookupResult::notFound();

        self::assertTrue($found->isFound());
        self::assertSame($complete, $found->record());
        self::assertFalse($notFound->isFound());
        self::assertNull($notFound->record());
        self::assertNotSame($notFound, AuditLookupResult::notFound());
        $this->assertSerializationRejected($found);
        $this->assertSerializationRejected($notFound);
    }

    public function testSegmentCloseResultExposesOnlyTypedGetters(): void
    {
        $result = new AuditSegmentCloseResult(
            'system:global',
            '018f0a00-0000-7000-8000-000000000002',
            '018f0a00-0000-7000-8000-000000000003',
            1,
            2,
            str_repeat('a', 64),
            str_repeat('b', 64),
            'aft:v1:' . str_repeat('c', 64),
            '2026-08-29T12:34:56.123456Z',
            true,
        );

        self::assertSame('system:global', $result->partitionId());
        self::assertSame('018f0a00-0000-7000-8000-000000000002', $result->closedSegmentId());
        self::assertSame('018f0a00-0000-7000-8000-000000000003', $result->successorSegmentId());
        self::assertSame(1, $result->firstSequence());
        self::assertSame(2, $result->finalSequence());
        self::assertSame(str_repeat('a', 64), $result->predecessorRecordHash());
        self::assertSame(str_repeat('b', 64), $result->headHash());
        self::assertSame('aft:v1:' . str_repeat('c', 64), $result->closeFencingToken());
        self::assertSame('2026-08-29T12:34:56.123456Z', $result->closedAt());
        self::assertTrue($result->changed());
        self::assertSame([], $result->__debugInfo());
        self::assertSame([], $result->__serialize());
        self::assertFalse(method_exists($result, '__set_state'));
        $this->assertSerializationRejected($result);
    }

    private function assertSerializationRejected(object $value): void
    {
        try {
            unserialize(serialize($value));
            self::fail('sensitive result unexpectedly deserialized');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('integrity_invalid', $exception->code());
            self::assertNull($exception->getPrevious());
        }
    }

    private function storedRecord(): AuditStoredRecord
    {
        return AuditStoredRecord::fromArray([
            'schemaVersion' => 1,
            'kind' => 'audit_stored_record',
            'recordId' => '018f0a00-0000-7000-8000-000000000001',
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'segmentId' => '018f0a00-0000-7000-8000-000000000002',
            'sequence' => 1,
            'previousRecordHash' => null,
            'contentHash' => str_repeat('a', 64),
            'recordHash' => str_repeat('b', 64),
            'recordedAt' => '2026-08-29T12:34:56.123456Z',
            'eventCategory' => 'business',
            'protectedBody' => "BOPD\0" . str_repeat('p', 48),
        ]);
    }

    private function definition(): AuditEventDefinition
    {
        return new AuditEventDefinition(
            'security.test',
            1,
            1,
            AuditEventCategory::Security,
            ['security.test'],
            [new AuditSemanticTuple(
                'security.test',
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [],
            13,
            AuditDelivery::Durable,
        );
    }
}
