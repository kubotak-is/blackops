<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Rotation;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditWriter;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowAudit;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowRequest;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowStorage;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationTarget;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionProperty;

final class StorageRotationAuditHookTest extends TestCase
{
    private const string AUDIT_ID = '0123456789abcdef0123456789abcdef';
    private const string CURSOR = '019f80ab-4000-7000-8000-000000000011';
    private const string CANARY = 'rotation-hook-trace-canary';

    public function testEntryAdmissionIsReadOnlyAndUsesTheBoundConnection(): void
    {
        $clock = new RotationHookClock();
        $connection = $this->connection(true, false, 0);
        $hook = $this->hook($clock, $connection);

        $hook->assertEntry($connection, $this->scope());

        self::assertSame(0, $clock->calls);
    }

    public function testEntryRejectsAmbientTransactionBeforeNativeWork(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook(new RotationHookClock(), $connection);

        try {
            $hook->assertEntry($connection, $this->scope());
            self::fail('ambient transaction was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testRowAdmissionRejectsPartialTenantBeforeDecode(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook(new RotationHookClock(), $connection);
        $target = PostgreSqlStorageProtectionRotationTarget::forPurpose(StoragePurpose::JournalRecord);
        $request = new PostgreSqlStorageProtectionRotationRowRequest(
            $this->scope(),
            StorageProtectionRotationMode::Confirmed,
            new PostgreSqlStorageProtectionRotationRowStorage($target, 'blackops.journal'),
            new PostgreSqlStorageProtectionRotationRowAudit(
                'blackops.storage_protection_rotation_checkpoints',
                'blackops.storage_protection_rotation_audits',
                self::AUDIT_ID,
                new StorageProtectionRotationCounts(0, 0, 0, 0),
            ),
            [
                'record_id' => self::CURSOR,
                'operation_id' => self::CURSOR,
                'operation_type' => 'fixture.operation',
                'operation_schema_version' => 1,
                'tenant_type' => 'account',
                'tenant_id' => null,
                'encoded_record' => 'synthetic-canary',
            ],
        );

        try {
            $hook->assertRow($connection, $request);
            self::fail('partial tenant metadata was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testProgressRequiresTheSelectedRowCursorAndTenantPair(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook(new RotationHookClock(), $connection);
        $target = PostgreSqlStorageProtectionRotationTarget::forPurpose(StoragePurpose::JournalRecord);
        $request = new PostgreSqlStorageProtectionRotationRowRequest(
            $this->scope(),
            StorageProtectionRotationMode::Confirmed,
            new PostgreSqlStorageProtectionRotationRowStorage($target, 'blackops.journal'),
            new PostgreSqlStorageProtectionRotationRowAudit(
                'checkpoint',
                'audit',
                self::AUDIT_ID,
                new StorageProtectionRotationCounts(0, 0, 0, 0),
            ),
            [
                'record_id' => self::CURSOR,
                'operation_id' => self::CURSOR,
                'operation_type' => 'fixture.operation',
                'operation_schema_version' => 1,
                'tenant_type' => null,
                'tenant_id' => null,
                'encoded_record' => 'synthetic-canary',
            ],
        );

        try {
            $hook->progressed($connection, $request, '019f80ab-4000-7000-8000-000000000012');
            self::fail('a cursor different from the selected row was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testNonCompositeIdentityRejectsACompositeCursorBeforeDecode(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook(new RotationHookClock(), $connection);
        $target = PostgreSqlStorageProtectionRotationTarget::forPurpose(StoragePurpose::JournalRecord);
        $request = new PostgreSqlStorageProtectionRotationRowRequest(
            $this->scope(),
            StorageProtectionRotationMode::Confirmed,
            new PostgreSqlStorageProtectionRotationRowStorage($target, 'blackops.journal'),
            new PostgreSqlStorageProtectionRotationRowAudit(
                'checkpoint',
                'audit',
                self::AUDIT_ID,
                new StorageProtectionRotationCounts(0, 0, 0, 0),
            ),
            [
                'record_id' => '1:' . str_repeat('a', 64),
                'operation_id' => self::CURSOR,
                'operation_type' => 'fixture.operation',
                'operation_schema_version' => 1,
                'tenant_type' => null,
                'tenant_id' => null,
                'encoded_record' => 'synthetic-canary',
            ],
        );

        try {
            $hook->assertRow($connection, $request);
            self::fail('a composite cursor was accepted for a UUID target');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testRunningCompletionHasNoTerminalProjection(): void
    {
        $clock = new RotationHookClock();
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook($clock, $connection);
        $hook->finished(
            $connection,
            new StorageProtectionRotationAuditCompletion(
                $this->scope(),
                new StorageProtectionRotationCounts(1, 1, 0, 0),
                'running',
                self::AUDIT_ID,
                null,
            ),
        );

        self::assertSame(0, $clock->calls);
    }

    public function testMalformedHookSnapshotFailsClosedWithoutLeakingItsWriter(): void
    {
        $hook = $this->hook(new RotationHookClock(), $this->connection(true, false, 0));
        $property = new ReflectionProperty($hook, 'snapshot');
        $property->setValue($hook, new \SensitiveParameterValue(['writer' => 'invalid']));
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse($previous);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));

        try {
            $hook->assertEntry($this->connection(true, false, 0), $this->scope(canary: true));
            self::fail('a malformed hook snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::CANARY, var_export($failure->getTrace(), true));
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
        }
        self::assertSame([], $hook->__debugInfo());
        self::assertSame([], $hook->__serialize());
    }

    private function scope(bool $canary = false): StorageProtectionRotationScope
    {
        return new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            null,
            'old:v1',
            'new:v1',
            1,
            'rotation-hook',
            actor: $canary ? self::CANARY : null,
            reason: $canary ? self::CANARY : null,
        );
    }

    private function connection(bool $autoCommit, bool $active, int $nesting): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn($autoCommit);
        $connection->method('isTransactionActive')->willReturn($active);
        $connection->method('getTransactionNestingLevel')->willReturn($nesting);

        return $connection;
    }

    private function hook(RotationHookClock $clock, Connection $connection): StorageRotationAuditHook
    {
        return new StorageRotationAuditHook(
            new StorageRotationAuditWriter(
                new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                $this->selector(),
                $this->coordinator($connection),
                $clock,
                new ActorContext(null, null, new ActorRef('execution-1', 'worker')),
                AuditRecordCorrelation::fromArray([]),
                AuditSourceRuntime::Worker,
                'rotation-hook-test',
            ),
        );
    }

    private function selector(): AuditDeliverySelector
    {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);

        return new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
    }

    private function coordinator(Connection $connection): AuditDeliveryCoordinator
    {
        $records = new \ReflectionClass(\BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new \ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $this->selector(), $records, $outbox);
    }
}

final class RotationHookClock implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-09-21T12:34:56.123456+09:00');
    }
}
