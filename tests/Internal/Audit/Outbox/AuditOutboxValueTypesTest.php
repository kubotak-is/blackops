<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Outbox\AuditOutboxAppendResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use PHPUnit\Framework\TestCase;

final class AuditOutboxValueTypesTest extends TestCase
{
    public function testRecordComposesTheExactFifteenFieldOrder(): void
    {
        $record = $this->record();

        self::assertSame(
            [
                'schemaVersion',
                'kind',
                'recordId',
                'partitionId',
                'tenantBinding',
                'ingressOrdinal',
                'idempotencyKeyHash',
                'contentHash',
                'state',
                'attempt',
                'availableAt',
                'leaseFencingToken',
                'leaseExpiresAt',
                'protectedBody',
                'version',
            ],
            array_keys($record->toArray()),
        );
        self::assertSame(1, $record->toArray()['schemaVersion']);
        self::assertSame('audit_outbox_record', $record->toArray()['kind']);
        self::assertSame($record->protectedIngress()->toArray(), [
            'recordId' => $record->toArray()['recordId'],
            'partitionId' => $record->toArray()['partitionId'],
            'tenantBinding' => $record->toArray()['tenantBinding'],
            'ingressOrdinal' => $record->toArray()['ingressOrdinal'],
            'idempotencyKeyHash' => $record->toArray()['idempotencyKeyHash'],
            'contentHash' => $record->toArray()['contentHash'],
            'protectedBody' => $record->toArray()['protectedBody'],
        ]);
        self::assertSame(
            AuditOutboxStateSnapshot::pending('2026-09-01T03:00:00.123456Z', new AuditOutboxConfiguration())->toArray(),
            $record->stateSnapshot()->toArray(),
        );
    }

    public function testRecordAndResultsKeepTrustedValuesDetachedBehindValueObjects(): void
    {
        $record = $this->record();
        $append = new AuditOutboxAppendResult($record, true);
        $found = AuditOutboxLookupResult::fromRecord($record);
        $missing = AuditOutboxLookupResult::notFound();

        self::assertTrue($append->inserted());
        self::assertSame($record, $append->record());
        self::assertTrue($found->isFound());
        self::assertSame($record, $found->record());
        self::assertFalse($missing->isFound());
        self::assertNull($missing->record());

        $array = $record->toArray();
        $array['protectedBody'] = 'changed';
        self::assertNotSame($array['protectedBody'], $record->toArray()['protectedBody']);
    }

    public function testExactClassModifiersAndConstructorsAreClosed(): void
    {
        $record = new \ReflectionClass(AuditOutboxRecord::class);
        $append = new \ReflectionClass(AuditOutboxAppendResult::class);
        $lookup = new \ReflectionClass(AuditOutboxLookupResult::class);
        $exception = new \ReflectionClass(AuditOutboxStoreException::class);

        self::assertTrue($record->isFinal());
        self::assertFalse($record->isReadOnly());
        self::assertTrue($append->isFinal());
        self::assertFalse($append->isReadOnly());
        self::assertTrue($lookup->isFinal());
        self::assertFalse($lookup->isReadOnly());
        self::assertTrue($exception->isFinal());
    }

    public function testAllFailureCasesHaveOneFixedValueFreeSurface(): void
    {
        foreach (AuditOutboxStoreFailure::cases() as $failure) {
            $exception = new AuditOutboxStoreException($failure);

            self::assertSame($failure, $exception->failure());
            self::assertSame($failure->value, $exception->code());
            self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testInspectionAndSerializationSurfacesDoNotExposeProtectedValues(): void
    {
        $record = $this->leasedRecord();
        $token = '018f0a00-0000-7000-8000-000000000001';
        $body = $record->toArray()['protectedBody'];
        self::assertIsString($body);

        ob_start();
        var_dump($record);
        $dump = ob_get_clean();
        self::assertIsString($dump);
        $surfaces = [
            $dump,
            print_r($record, true),
            var_export($record, true),
            json_encode($record, JSON_THROW_ON_ERROR),
            serialize($record),
            var_export(new AuditOutboxAppendResult($record, true), true),
            serialize(AuditOutboxLookupResult::fromRecord($record)),
        ];
        foreach ($surfaces as $surface) {
            self::assertStringNotContainsString($token, $surface);
            self::assertStringNotContainsString($body, $surface);
        }
        self::assertSame([], $record->__debugInfo());
        self::assertSame([], $record->__serialize());
        self::assertSame([], new AuditOutboxAppendResult($record, true)->__serialize());
    }

    public function testUnserializationAlwaysFailsWithTheSafeFailure(): void
    {
        $record = $this->record();

        try {
            $record->__unserialize([]);
            self::fail('record unserialization must fail closed');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        try {
            new AuditOutboxAppendResult($record, true)->__unserialize([]);
            self::fail('result unserialization must fail closed');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
        }
    }

    public function testExceptionTraceAndSerializationDoNotCarryTheInputCanary(): void
    {
        $canary = 'outbox-value-canary-018f0a00-0000-7000-8000-000000000001';
        $exception = new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        $surfaces = [
            $exception->getMessage(),
            (string) $exception->getPrevious(),
            $exception->getTraceAsString(),
            var_export($exception->getTrace(), true),
            print_r($exception, true),
            var_export($exception, true),
            json_encode($exception, JSON_THROW_ON_ERROR),
            serialize($exception),
            $canary,
        ];

        foreach (array_slice($surfaces, 0, -1) as $surface) {
            self::assertStringNotContainsString($canary, $surface);
        }
    }

    private function record(): AuditOutboxRecord
    {
        $ingress = AuditProtectedIngress::fromArray([
            'recordId' => '018f0a00-0000-7000-8000-000000000001',
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'ingressOrdinal' => 1,
            'idempotencyKeyHash' => str_repeat('a', 64),
            'contentHash' => str_repeat('b', 64),
            'protectedBody' => 'BOPD' . str_repeat('p', 49),
        ]);
        $state = AuditOutboxStateSnapshot::pending('2026-09-01T03:00:00.123456Z', new AuditOutboxConfiguration());

        return AuditOutboxRecord::fromParts($ingress, $state);
    }

    private function leasedRecord(): AuditOutboxRecord
    {
        $ingress = AuditProtectedIngress::fromArray([
            'recordId' => '018f0a00-0000-7000-8000-000000000001',
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'ingressOrdinal' => 1,
            'idempotencyKeyHash' => str_repeat('a', 64),
            'contentHash' => str_repeat('b', 64),
            'protectedBody' => 'BOPD' . str_repeat('p', 49),
        ]);
        $state = AuditOutboxStateSnapshot::fromArray([
            'state' => 'leased',
            'attempt' => 1,
            'availableAt' => null,
            'leaseFencingToken' => '018f0a00-0000-7000-8000-000000000001',
            'leaseExpiresAt' => '2026-09-01T03:05:00.123456Z',
            'version' => 2,
        ], new AuditOutboxConfiguration());

        return AuditOutboxRecord::fromParts($ingress, $state);
    }
}
