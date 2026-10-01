<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHold;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator as StoreUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\Retention\AuditedRetentionHoldPort;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuditedRetentionHoldPortTest extends TestCase
{
    private const string SCHEMA = 'blackops_retention_hold_audit';
    private const string OPERATION_TENANT = '019fca20-0000-7000-8000-000000000001';
    private const string OPERATION_GLOBAL = '019fca20-0000-7000-8000-000000000002';
    private const string OPERATION_INLINE = '019fca20-0000-7000-8000-000000000003';
    private const string HOLD_ONE = '019fca20-0000-7000-8000-000000000101';
    private const string HOLD_TWO = '019fca20-0000-7000-8000-000000000102';
    private const string HOLD_THREE = '019fca20-0000-7000-8000-000000000103';
    private const string HOLD_FOUR = '019fca20-0000-7000-8000-000000000104';
    private const string HOLD_FIVE = '019fca20-0000-7000-8000-000000000105';
    private const string HOLD_SIX = '019fca20-0000-7000-8000-000000000106';
    private const string HOLD_SEVEN = '019fca20-0000-7000-8000-000000000107';
    private const string BUILD_ID = 'd5h-native-build';
    private const string TENANT_TYPE = 'account';
    private const string TENANT_ID = 'tenant-canary';
    private const string WRITER_TIME = '2026-09-22T12:00:10.000000Z';
    private const string STORE_TIME = '2026-09-22T12:00:11.000000Z';
    private const string NATIVE_TIME = '2026-09-22T12:00:12.000000Z';
    private const string OPERATION_MISMATCH = '019fca20-0000-7000-8000-000000000099';

    private Connection $admin;
    private Connection $connection;
    private Connection $probe;
    private PostgreSqlDeferredOperationSchema $nativeSchema;
    private PostgreSqlAuditSchema $recordSchema;
    private PostgreSqlAuditOutboxSchema $outboxSchema;
    private AuditProtectedBodyCodec $protection;
    private D5NativeClock $nativeClock;
    private D5HoldIdGenerator $nativeIds;
    private D5WriterClock $writerClock;
    private RetentionHoldAuditWriter $writer;
    private AuditedRetentionHoldPort $adapter;

    protected function setUp(): void
    {
        $this->admin = $this->connection();
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        $this->admin->executeStatement('CREATE SCHEMA "' . self::SCHEMA . '"');
        $this->nativeSchema = new PostgreSqlDeferredOperationSchema(self::SCHEMA);
        $this->recordSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        $this->outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema(self::SCHEMA));
        foreach ([
            ...$this->nativeSchema->statements(),
            ...new PostgreSqlJournalSchema(self::SCHEMA)->statements(),
            ...$this->recordSchema->statements(),
            ...$this->outboxSchema->statements(),
        ] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $this->sender()->enqueue($this->message(OperationId::fromString(self::OPERATION_TENANT), $this->tenant()));

        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([
            self::HOLD_ONE,
            self::HOLD_TWO,
            self::HOLD_THREE,
            self::HOLD_FOUR,
            self::HOLD_FIVE,
            self::HOLD_SIX,
            self::HOLD_SEVEN,
        ]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
    }

    protected function tearDown(): void
    {
        foreach ([$this->connection ?? null, $this->probe ?? null] as $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            try {
                $this->restoreConnection($connection);
            } catch (Throwable) {
            }
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
        try {
            $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        } catch (Throwable) {
        }
        try {
            $this->admin->close();
        } catch (Throwable) {
        }
    }

    public function testNativePortAndAuditedPortShareTheRetentionContract(): void
    {
        self::assertInstanceOf(RetentionHoldPort::class, $this->adapter);
        $hold = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'hold-reason-canary',
            RetentionActorRef::fromString('legacy-actor-canary'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );

        self::assertSame(self::HOLD_ONE, $hold->id()->toString());
        self::assertCount(1, $this->adapter->activeFor($hold->operationId()));
        self::assertSame(1, $this->holdCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(
            [
                self::HOLD_TWO,
                self::HOLD_THREE,
                self::HOLD_FOUR,
                self::HOLD_FIVE,
                self::HOLD_SIX,
                self::HOLD_SEVEN,
            ],
            $this->nativeIds->remaining(),
        );
        self::assertSame(1, $this->writerClock->calls);
        $this->assertLogicalEvent(
            $this->logicalRows()[0],
            'retention.hold.placed',
            'retention_hold_placed',
            $hold,
            $this->tenant(),
        );
        $logicalJson = json_encode($this->logicalRows()[0]['logicalRecord'], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('hold-reason-canary', $logicalJson);
        self::assertStringNotContainsString('legacy-actor-canary', $logicalJson);
    }

    public function testPlaceAndReleaseUseGlobalTenantAndInlineJournalSubjects(): void
    {
        $global = OperationId::fromString(self::OPERATION_GLOBAL);
        $inline = OperationId::fromString(self::OPERATION_INLINE);
        $this->sender()->enqueue($this->message($global, null));
        $this->seedInlineJournal($inline);

        $tenantHold = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Security,
            'tenant hold',
            RetentionActorRef::fromString('tenant-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $globalHold = $this->adapter->place(
            $global,
            RetentionHoldCategory::Audit,
            'global hold',
            RetentionActorRef::fromString('global-actor'),
            new DateTimeImmutable('2026-09-22T11:59:01Z'),
        );
        $inlineHold = $this->adapter->place(
            $inline,
            RetentionHoldCategory::Support,
            'inline hold',
            RetentionActorRef::fromString('inline-actor'),
            new DateTimeImmutable('2026-09-22T11:59:02Z'),
        );

        self::assertSame(self::HOLD_ONE, $tenantHold->id()->toString());
        self::assertSame(self::HOLD_TWO, $globalHold->id()->toString());
        self::assertSame(self::HOLD_THREE, $inlineHold->id()->toString());
        self::assertFalse(
            $this->adapter
                ->release(
                    $tenantHold->id(),
                    RetentionActorRef::fromString('tenant-release'),
                    new DateTimeImmutable('2026-09-22T12:01:00Z'),
                )
                ->isActive(),
        );
        self::assertFalse(
            $this->adapter
                ->release(
                    $globalHold->id(),
                    RetentionActorRef::fromString('global-release'),
                    new DateTimeImmutable('2026-09-22T12:01:01Z'),
                )
                ->isActive(),
        );
        self::assertFalse(
            $this->adapter
                ->release(
                    $inlineHold->id(),
                    RetentionActorRef::fromString('inline-release'),
                    new DateTimeImmutable('2026-09-22T12:01:02Z'),
                )
                ->isActive(),
        );

        $events = $this->logicalRows();
        self::assertCount(6, $events);
        $this->assertLogicalEvent(
            $events[0],
            'retention.hold.placed',
            'retention_hold_placed',
            $tenantHold,
            $this->tenant(),
        );
        $this->assertLogicalEvent($events[1], 'retention.hold.placed', 'retention_hold_placed', $globalHold, null);
        $this->assertLogicalEvent($events[2], 'retention.hold.placed', 'retention_hold_placed', $inlineHold, null);
        $this->assertLogicalEvent(
            $events[3],
            'retention.hold.released',
            'retention_hold_released',
            $tenantHold,
            $this->tenant(),
        );
        $this->assertLogicalEvent($events[4], 'retention.hold.released', 'retention_hold_released', $globalHold, null);
        $this->assertLogicalEvent($events[5], 'retention.hold.released', 'retention_hold_released', $inlineHold, null);
        self::assertSame(3, $this->nativeClock->calls);
        self::assertSame(3, $this->nativeIds->calls);
        self::assertSame(
            [
                self::HOLD_FOUR,
                self::HOLD_FIVE,
                self::HOLD_SIX,
                self::HOLD_SEVEN,
            ],
            $this->nativeIds->remaining(),
        );
        self::assertSame(6, $this->writerClock->calls);
    }

    public function testReleaseUsesPersistedTenantAfterSourceDeletion(): void
    {
        $hold = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'source deletion hold',
            RetentionActorRef::fromString('source-delete-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $this->admin->executeStatement(
            'DELETE FROM ' . $this->nativeSchema->operationsTable() . ' WHERE operation_id = :operation_id',
            ['operation_id' => self::OPERATION_TENANT],
        );

        $released = $this->adapter->release(
            $hold->id(),
            RetentionActorRef::fromString('source-delete-release'),
            new DateTimeImmutable('2026-09-22T12:02:00Z'),
        );

        self::assertFalse($released->isActive());
        self::assertCount(2, $this->logicalRows());
        $this->assertLogicalEvent(
            $this->logicalRows()[1],
            'retention.hold.released',
            'retention_hold_released',
            $hold,
            $this->tenant(),
        );
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(2, $this->writerClock->calls);
    }

    public function testAppendFailureRollsBackHoldAndAuditIngress(): void
    {
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->recordsTable() . ' CASCADE');
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->partitionStateTable() . ' CASCADE');

        $this->assertFailure(fn(): RetentionHold => $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Audit,
            'append failure hold',
            RetentionActorRef::fromString('append-failure-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        ));

        self::assertSame(0, $this->holdCount());
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->writerClock->calls);
    }

    public function testPartialPersistedTenantRollsBackReleaseWithoutAnEvent(): void
    {
        $native = new PostgreSqlRetentionHoldStore(
            $this->connection,
            self::SCHEMA,
            new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME)),
            new D5HoldIdGenerator([self::HOLD_ONE]),
        );
        $hold = $native->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'partial subject hold',
            RetentionActorRef::fromString('partial-subject-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $this->admin->executeStatement(
            'ALTER TABLE '
            . $this->nativeSchema->retentionHoldsTable()
            . ' DROP CONSTRAINT IF EXISTS retention_holds_tenant_pair_check',
        );
        $this->admin->executeStatement(
            'UPDATE '
            . $this->nativeSchema->retentionHoldsTable()
            . ' SET tenant_type = :tenant_type, tenant_id = NULL WHERE hold_id = :hold_id',
            ['tenant_type' => self::TENANT_TYPE, 'hold_id' => self::HOLD_ONE],
        );

        $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
            $hold->id(),
            RetentionActorRef::fromString('partial-subject-release'),
            new DateTimeImmutable('2026-09-22T12:02:00Z'),
        ));

        self::assertNull($this->holdRow(self::HOLD_ONE)['released_at']);
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testPersistedControlTenantRollsBackReleaseWithoutIngress(): void
    {
        $native = new PostgreSqlRetentionHoldStore(
            $this->connection,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
        $hold = $native->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'persisted-control-subject hold',
            RetentionActorRef::fromString('persisted-control-subject-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $storedTenantId = self::TENANT_ID . "\u{0001}";
        $this->admin->executeStatement(
            'UPDATE '
            . $this->nativeSchema->retentionHoldsTable()
            . ' SET tenant_id = :tenant_id WHERE hold_id = :hold_id',
            ['tenant_id' => $storedTenantId, 'hold_id' => self::HOLD_ONE],
        );

        $before = $this->holdRow(self::HOLD_ONE);
        self::assertSame(self::TENANT_TYPE, $before['tenant_type']);
        self::assertSame($storedTenantId, $before['tenant_id']);
        self::assertNull($before['released_at']);

        $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
            $hold->id(),
            RetentionActorRef::fromString('persisted-control-subject-release'),
            new DateTimeImmutable('2026-09-22T12:02:00Z'),
        ));

        $after = $this->holdRow(self::HOLD_ONE);
        self::assertSame(self::TENANT_TYPE, $after['tenant_type']);
        self::assertSame($storedTenantId, $after['tenant_id']);
        self::assertNull($after['released_at']);
        self::assertSame(0, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testMissingNonCanonicalMismatchedAndInvalidTypedSubjectsRollbackRelease(): void
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5SubjectFaultConnection($params, $driver, $this->nativeSchema->retentionHoldsTable());
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([
            self::HOLD_ONE,
            self::HOLD_TWO,
            self::HOLD_THREE,
            self::HOLD_FOUR,
            self::HOLD_FIVE,
            self::HOLD_SIX,
            self::HOLD_SEVEN,
        ]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $native = new PostgreSqlRetentionHoldStore(
            $this->connection,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
        $subjects = [
            [self::HOLD_ONE, false],
            [
                self::HOLD_TWO,
                ['operation_id' => self::OPERATION_TENANT, 'tenant_type' => self::TENANT_TYPE, 'tenant_id' => null],
            ],
            [
                self::HOLD_THREE,
                [
                    'operation_id' => self::OPERATION_TENANT,
                    'tenant_type' => ' ' . self::TENANT_TYPE,
                    'tenant_id' => self::TENANT_ID,
                ],
            ],
            [
                self::HOLD_FOUR,
                [
                    'operation_id' => self::OPERATION_MISMATCH,
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => self::TENANT_ID,
                ],
            ],
            [
                self::HOLD_FIVE,
                [
                    'operation_id' => self::OPERATION_TENANT,
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => self::TENANT_ID . "\u{0001}",
                ],
            ],
            [
                self::HOLD_SIX,
                [
                    'operation_id' => self::OPERATION_TENANT,
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => "tenant-e\u{0301}",
                ],
            ],
            [
                self::HOLD_SEVEN,
                [
                    'operation_id' => self::OPERATION_TENANT,
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => str_repeat('x', 257),
                ],
            ],
        ];

        foreach ($subjects as [$holdId, $row]) {
            $hold = $native->place(
                OperationId::fromString(self::OPERATION_TENANT),
                RetentionHoldCategory::Legal,
                'subject-shape-hold',
                RetentionActorRef::fromString('subject-shape-actor'),
                new DateTimeImmutable('2026-09-22T11:59:00Z'),
            );
            self::assertSame($holdId, $hold->id()->toString());
            $this->connection->armSubjectResult($row);
            $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
                $hold->id(),
                RetentionActorRef::fromString('subject-shape-release'),
                new DateTimeImmutable('2026-09-22T12:02:00Z'),
            ));
            self::assertNull($this->holdRow($holdId)['released_at']);
        }

        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(7, $this->nativeClock->calls);
        self::assertSame(7, $this->nativeIds->calls);
        self::assertSame([], $this->nativeIds->remaining());
    }

    public function testNativeMutationFailureRollsBackBeforeWriterClock(): void
    {
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE], new \RuntimeException('native-id-canary'));
        $this->adapter = new AuditedRetentionHoldPort(
            $this->connection,
            $this->writer,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );

        $this->assertFailure(fn(): RetentionHold => $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'native-failure-canary',
            RetentionActorRef::fromString('native-failure-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        ));

        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testWriterClockFailureRollsBackNativeMutation(): void
    {
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE]);
        $this->writerClock = new D5WriterClock(
            new DateTimeImmutable(self::WRITER_TIME),
            new \RuntimeException('writer-clock-canary'),
        );
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);

        $this->assertFailure(fn(): RetentionHold => $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'writer-clock-canary',
            RetentionActorRef::fromString('writer-clock-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        ));

        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
    }

    public function testSelectorFailureRollsBackNativeMutationBeforeAppend(): void
    {
        $lookup = new ImmutableAuditEventCatalogLookup([]);
        $brokenSelector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->writer = $this->writer($brokenSelector, $bundle['delivery'], $this->writerClock);
        $this->adapter = new AuditedRetentionHoldPort(
            $this->connection,
            $this->writer,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );

        $this->assertFailure(fn(): RetentionHold => $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'selector-failure-canary',
            RetentionActorRef::fromString('selector-failure-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        ));

        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
    }

    public function testMissingReleaseRollsBackWithoutNativeOrWriterWork(): void
    {
        $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
            RetentionHoldId::fromString(self::HOLD_FOUR),
            RetentionActorRef::fromString('missing-release-actor'),
            new DateTimeImmutable('2026-09-22T12:02:00Z'),
        ));

        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testActiveForAndConstructionPerformNoAuditOrNativeClockWork(): void
    {
        $constructed = new AuditedRetentionHoldPort(
            $this->connection,
            $this->writer,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
        self::assertInstanceOf(RetentionHoldPort::class, $constructed);
        $active = $this->adapter->activeFor(OperationId::fromString(self::OPERATION_TENANT));
        self::assertSame([], $active);
        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testCommittedThenThrowLeavesExactlyOneHoldAndIngressWithoutRetry(): void
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5CommitThenThrowingConnection($params, $driver);
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $this->connection->arm();

        $this->assertFailure(fn(): RetentionHold => $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Security,
            'commit ambiguity hold',
            RetentionActorRef::fromString('commit-ambiguity-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        ));

        self::assertSame(1, $this->holdCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(1, count($this->logicalRows()));
        self::assertSame([], $this->nativeIds->remaining());
    }

    public function testCommittedThenThrowReleaseLeavesReleasedHoldAndIngressWithoutRetry(): void
    {
        $hold = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Security,
            'release-commit-ambiguity-hold',
            RetentionActorRef::fromString('release-commit-ambiguity-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        self::assertSame(1, $this->outboxCount());

        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5CommitThenThrowingConnection($params, $driver);
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_TWO]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection, self::AUDIT_IDS_LATE);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $this->connection->arm();

        $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
            $hold->id(),
            RetentionActorRef::fromString('release-commit-ambiguity-release'),
            new DateTimeImmutable('2026-09-22T12:02:00Z'),
        ));

        self::assertNotNull($this->holdRow(self::HOLD_ONE)['released_at']);
        self::assertSame(2, $this->outboxCount());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertCount(2, $this->logicalRows());
    }

    public function testRepeatedPlaceUsesFreshHoldAndReleasedHoldProducesNoSecondEvent(): void
    {
        $first = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Audit,
            'repeat-one',
            RetentionActorRef::fromString('repeat-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $second = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Audit,
            'repeat-two',
            RetentionActorRef::fromString('repeat-actor'),
            new DateTimeImmutable('2026-09-22T11:59:01Z'),
        );
        $this->adapter->release(
            $first->id(),
            RetentionActorRef::fromString('repeat-release'),
            new DateTimeImmutable('2026-09-22T12:01:00Z'),
        );
        $outboxCount = $this->outboxCount();

        $this->assertFailure(fn(): RetentionHold => $this->adapter->release(
            $first->id(),
            RetentionActorRef::fromString('repeat-release-again'),
            new DateTimeImmutable('2026-09-22T12:01:01Z'),
        ));

        self::assertNotSame($first->id()->toString(), $second->id()->toString());
        self::assertSame(2, $this->holdCount());
        self::assertSame($outboxCount, $this->outboxCount());
        self::assertCount(1, $this->adapter->activeFor($second->operationId()));
        self::assertSame(self::HOLD_TWO, $this->adapter->activeFor($second->operationId())[0]->id()->toString());
        self::assertSame(2, $this->nativeClock->calls);
        self::assertSame(2, $this->nativeIds->calls);
        self::assertSame(
            [
                self::HOLD_THREE,
                self::HOLD_FOUR,
                self::HOLD_FIVE,
                self::HOLD_SIX,
                self::HOLD_SEVEN,
            ],
            $this->nativeIds->remaining(),
        );
        self::assertSame(3, $this->writerClock->calls);
        $events = $this->logicalRows();
        self::assertCount(3, $events);
        $this->assertLogicalEvent(
            $events[0],
            'retention.hold.placed',
            'retention_hold_placed',
            $first,
            $this->tenant(),
        );
        $this->assertLogicalEvent(
            $events[1],
            'retention.hold.placed',
            'retention_hold_placed',
            $second,
            $this->tenant(),
        );
        $this->assertLogicalEvent(
            $events[2],
            'retention.hold.released',
            'retention_hold_released',
            $first,
            $this->tenant(),
        );
    }

    public function testAmbientRootIsRejectedBeforeNativeAndWriterWork(): void
    {
        $this->connection->beginTransaction();
        try {
            $this->assertAdmissionFailure();
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testNestedRootIsRejectedBeforeNativeAndWriterWork(): void
    {
        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        try {
            self::assertSame(2, $this->connection->getTransactionNestingLevel());
            $this->assertAdmissionFailure();
        } finally {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testNonAutocommitIsRejectedBeforeNativeAndWriterWork(): void
    {
        $this->connection->setAutoCommit(false);
        $this->connection->beginTransaction();
        try {
            $this->assertAdmissionFailure();
        } finally {
            $this->restoreConnection($this->connection);
        }
        self::assertTrue($this->connection->isAutoCommit());
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testAdmissionRejectsWithNoSqlOnAnActiveConnection(): void
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5CountingConnection($params, $driver);
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $this->connection->beginTransaction();
        $this->connection->resetSqlCounters();

        try {
            $this->adapter->place(
                OperationId::fromString(self::OPERATION_TENANT),
                RetentionHoldCategory::Legal,
                'zero-sql-admission-canary',
                RetentionActorRef::fromString('zero-sql-admission-actor'),
                new DateTimeImmutable('2026-09-22T11:59:00Z'),
            );
            self::fail('The ambient root was accepted.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        } finally {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }

        self::assertSame(0, $this->connection->sqlCalls());
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testWriterSeesOwnedRootAndProbeCannotSeeIngressBeforeCommit(): void
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5TransactionObservationConnection(
            $params,
            $driver,
            $this->probe,
            $this->outboxSchema->recordsTable(),
        );
        $this->nativeClock = new D5NativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5HoldIdGenerator([self::HOLD_ONE]);
        $this->writerClock = new D5WriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $this->connection->armObservation();

        $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'transaction-visibility-canary',
            RetentionActorRef::fromString('transaction-visibility-actor'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );

        self::assertTrue($this->connection->observed);
        self::assertSame(1, $this->holdCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
    }

    public function testOwnedFailureDoesNotExposeTraceArgumentsWhenTraceArgumentsAreEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        $this->writerClock = new D5WriterClock(
            new DateTimeImmutable(self::WRITER_TIME),
            new \RuntimeException('trace-cause-canary'),
        );
        $bundle = $this->auditBundle($this->connection);
        $this->protection = $bundle['protection'];
        $this->adapter = $this->adapterFromBundle($bundle);
        $failure = null;
        try {
            $this->adapter->place(
                OperationId::fromString(self::OPERATION_TENANT),
                RetentionHoldCategory::Legal,
                'trace-argument-canary',
                RetentionActorRef::fromString('trace-argument-actor'),
                new DateTimeImmutable('2026-09-22T11:59:00Z'),
            );
            self::fail('The writer clock failure was not mapped.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        $ownedFrames = array_values(array_filter($failure->getTrace(), static fn(array $frame): bool => in_array(
            $frame['class'] ?? null,
            [AuditedRetentionHoldPort::class, RetentionHoldAuditWriter::class],
            true,
        )));
        self::assertNotEmpty($ownedFrames);
        $protectedArguments = [];
        foreach ($ownedFrames as $frame) {
            foreach ($frame['args'] ?? [] as $argument) {
                if ($argument instanceof \SensitiveParameterValue) {
                    $protectedArguments[] = $argument;
                }
            }
        }
        self::assertNotEmpty($protectedArguments);
        $ownedTrace = print_r($ownedFrames, true) . var_export($ownedFrames, true);
        self::assertStringContainsString('SensitiveParameterValue', $ownedTrace);
        self::assertStringNotContainsString('trace-argument-canary', $ownedTrace);
        self::assertStringNotContainsString('trace-cause-canary', $ownedTrace);
        self::assertStringNotContainsString('trace-argument-canary', $failure->getTraceAsString());
        $ownedFramesSerializationFailure = null;
        try {
            serialize($ownedFrames);
        } catch (\Throwable $caught) {
            $ownedFramesSerializationFailure = $caught;
        }
        self::assertInstanceOf(\Throwable::class, $ownedFramesSerializationFailure);
        self::assertStringContainsString('SensitiveParameterValue', $ownedFramesSerializationFailure->getMessage());

        $failureSerializationFailure = null;
        try {
            serialize($failure);
        } catch (\Throwable $caught) {
            $failureSerializationFailure = $caught;
        }
        self::assertInstanceOf(\Throwable::class, $failureSerializationFailure);
        self::assertStringContainsString('SensitiveParameterValue', $failureSerializationFailure->getMessage());
        self::assertStringNotContainsString('trace-argument-canary', $failureSerializationFailure->getMessage());
        self::assertSame('trace-cause-canary', $this->writerClock->failureMessage());
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testWrongWriterConnectionAndInvalidConstructionAreValueFree(): void
    {
        $wrongBundle = $this->auditBundle($this->probe);
        $wrongWriter = $this->writer($wrongBundle['selector'], $wrongBundle['delivery'], $this->writerClock);
        $wrongAdapter = new AuditedRetentionHoldPort(
            $this->connection,
            $wrongWriter,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
        try {
            $wrongAdapter->place(
                OperationId::fromString(self::OPERATION_TENANT),
                RetentionHoldCategory::Legal,
                'wrong-connection-canary',
                RetentionActorRef::fromString('wrong-connection-actor'),
                new DateTimeImmutable('2026-09-22T11:59:00Z'),
            );
            self::fail('A writer bound to another Connection must be rejected.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('wrong-connection-canary', $failure->getTraceAsString());
        }
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->writerClock->calls);

        try {
            new AuditedRetentionHoldPort(
                $this->connection,
                $this->writer,
                'invalid-schema-name!',
                $this->nativeClock,
                $this->nativeIds,
            );
            self::fail('An invalid schema must fail during construction.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('invalid-schema-name!', $failure->getTraceAsString());
        }
    }

    public function testAdapterPrivacyHooksDoNotExposeOwnedGraphsOrLegacyInputs(): void
    {
        $hold = $this->adapter->place(
            OperationId::fromString(self::OPERATION_TENANT),
            RetentionHoldCategory::Legal,
            'privacy-reason-canary',
            RetentionActorRef::fromString('privacy-legacy-actor-canary'),
            new DateTimeImmutable('2026-09-22T11:59:00Z'),
        );
        $dependencyCanary = self::HOLD_TWO;
        self::assertContains($dependencyCanary, $this->nativeIds->remaining());
        $exported = var_export($this->adapter, true) . serialize($this->adapter);
        self::assertStringNotContainsString('privacy-reason-canary', $exported);
        self::assertStringNotContainsString('privacy-legacy-actor-canary', $exported);
        self::assertStringNotContainsString($dependencyCanary, $exported);
        self::assertSame([], $this->adapter->__debugInfo());
        self::assertSame([], $this->adapter->__serialize());
        self::assertSame(self::HOLD_ONE, $hold->id()->toString());
        $logical = $this->logicalRows()[0]['logicalRecord'];
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
            array_keys($logical),
        );
        $logicalJson = json_encode($logical, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('privacy-reason-canary', $logicalJson);
        self::assertStringNotContainsString('privacy-legacy-actor-canary', $logicalJson);

        $serialized = serialize($this->adapter);
        $this->assertAdapterFailure(fn(): mixed => unserialize($serialized, ['allowed_classes' => true]));
        $this->assertAdapterFailure(fn(): mixed => clone $this->adapter);

        $nativeCalls = $this->nativeClock->calls;
        $nativeIdCalls = $this->nativeIds->calls;
        $writerCalls = $this->writerClock->calls;
        $countingConnection = new D5CountingConnection($this->connection->getParams(), $this->connection->getDriver());
        try {
            $reflection = new \ReflectionClass(AuditedRetentionHoldPort::class);
            $uninitialized = $reflection->newInstanceWithoutConstructor();
            $this->assertAdapterFailure(fn(): array => $uninitialized->activeFor(OperationId::fromString(self::OPERATION_TENANT)));
            $corrupt = $reflection->newInstanceWithoutConstructor();
            new \ReflectionProperty(
                AuditedRetentionHoldPort::class,
                'snapshot',
            )->setValue($corrupt, new \SensitiveParameterValue([
                'connection' => $countingConnection,
                'dependencyCanary' => $dependencyCanary,
            ]));
            $this->assertAdapterFailure(fn(): array => $corrupt->activeFor(OperationId::fromString(self::OPERATION_TENANT)));
            self::assertSame(0, $countingConnection->sqlCalls());
        } finally {
            $countingConnection->close();
        }
        self::assertSame($nativeCalls, $this->nativeClock->calls);
        self::assertSame($nativeIdCalls, $this->nativeIds->calls);
        self::assertSame($writerCalls, $this->writerClock->calls);
    }

    private function assertAdmissionFailure(): void
    {
        try {
            $this->adapter->place(
                OperationId::fromString(self::OPERATION_TENANT),
                RetentionHoldCategory::Legal,
                'admission-canary',
                RetentionActorRef::fromString('admission-actor'),
                new DateTimeImmutable('2026-09-22T11:59:00Z'),
            );
            self::fail('The invalid transaction admission was accepted.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('admission-canary', $failure->getTraceAsString());
        }
        self::assertSame(0, $this->holdCount());
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->outboxCount());
    }

    private function restoreConnection(Connection $connection): void
    {
        if (!$connection->isAutoCommit()) {
            while ($connection->isTransactionActive() && $connection->getTransactionNestingLevel() > 1) {
                $connection->rollBack();
            }
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->setAutoCommit(true);
        }

        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /** @param callable():RetentionHold $operation */
    private function assertFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('The audited retention mutation unexpectedly succeeded.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /** @param callable():mixed $operation */
    private function assertAdapterFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('The adapter failure unexpectedly returned.');
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to deliver audited PostgreSQL retention hold.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /** @return array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator,protection:AuditProtectedBodyCodec} */
    private function auditBundle(Connection $connection, ?array $auditIds = null): array
    {
        $jcs = new AuditJcsCodec();
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $validator = new AuditRecordValidator($lookup);
        $recordsCodec = new AuditRecordCodec($validator, $jcs);
        $partitions = new AuditPartitionIdDeriver($jcs);
        $finalizer = new AuditRecordFinalizer($validator, $recordsCodec);
        $ingress = new AuditIngressEnvelopeCodec($recordsCodec, $partitions, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new D5AuditKeySource(),
            new D5AuditNonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $ingress,
        );
        $storeClock = new D5StoreClock(new DateTimeImmutable(self::STORE_TIME));
        $uuids = new D5StoreUuidGenerator($auditIds ?? self::AUDIT_IDS);
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $this->recordSchema,
            $storeClock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $recordsCodec,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $connection,
            $this->outboxSchema,
            $storeClock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator()),
            new \BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration(),
        );
        $selector = new AuditDeliverySelector($lookup, $validator);

        return [
            'selector' => $selector,
            'delivery' => new AuditDeliveryCoordinator($connection, $selector, $recordStore, $outboxStore),
            'protection' => $protection,
        ];
    }

    /** @param array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator,protection:AuditProtectedBodyCodec} $bundle */
    private function adapterFromBundle(array $bundle): AuditedRetentionHoldPort
    {
        $this->writer = $this->writer($bundle['selector'], $bundle['delivery'], $this->writerClock);

        return new AuditedRetentionHoldPort(
            $this->connection,
            $this->writer,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
    }

    private function writer(
        AuditDeliverySelector $selector,
        AuditDeliveryCoordinator $delivery,
        ClockInterface $clock,
    ): RetentionHoldAuditWriter {
        return new RetentionHoldAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $selector,
            $delivery,
            $clock,
            new ActorContext(
                new ActorRef('origin-canary', 'service'),
                new ActorRef('bound-authorized', 'operator'),
                new ActorRef('execution-canary', 'retention'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => 'correlation-canary']),
            AuditSourceRuntime::Worker,
            self::BUILD_ID,
        );
    }

    private function sender(): PostgreSqlDeferredOperationSender
    {
        return new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            new DateTimeImmutable('2026-09-22T11:58:00Z'),
        );
    }

    private function message(OperationId $operationId, ?TenantRef $tenant): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            $operationId,
            'retention.test',
            1,
            '{"payload":"retention-input"}',
            '{"context":"retention-context"}',
            new DateTimeImmutable('2026-09-22T11:58:00Z'),
            $tenant,
            new ActorRef('legacy-origin', 'worker'),
        );
    }

    private function tenant(): TenantRef
    {
        return new TenantRef(self::TENANT_TYPE, self::TENANT_ID);
    }

    private function seedInlineJournal(OperationId $operationId): void
    {
        $this->sender()->enqueue($this->message($operationId, null));
        $this->admin->executeStatement(
            'DELETE FROM ' . $this->nativeSchema->operationsTable() . ' WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $recordId = '019fca20-0000-7000-8000-000000000201';
        $this->admin->executeStatement(
            'INSERT INTO ' . new PostgreSqlJournalSchema(self::SCHEMA)->journalTable() . ' (
                record_id, operation_id, operation_type, sequence, event, attempt_id,
                schema_version, operation_schema_version, occurred_at, tenant_type, tenant_id,
                origin_actor_type, origin_actor_id, encoded_record
            ) VALUES (
                :record_id, :operation_id, :operation_type, 1, :event, NULL,
                1, 1, :occurred_at, NULL, NULL, NULL, NULL, :encoded_record
            )',
            [
                'record_id' => $recordId,
                'operation_id' => $operationId->toString(),
                'operation_type' => 'retention.inline',
                'event' => 'operation.received',
                'occurred_at' => '2026-09-22 11:58:00+00',
                'encoded_record' => PostgreSqlTestStorageProtection::journalEnvelope(
                    '{}',
                    $recordId,
                    $operationId->toString(),
                    'retention.inline',
                ),
            ],
            ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
        );
    }

    private function holdCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->nativeSchema->retentionHoldsTable());
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->outboxSchema->recordsTable());
    }

    /** @return array<string,mixed> */
    private function holdRow(string $holdId): array
    {
        $row = $this->probe->fetchAssociative(
            'SELECT hold_id::text AS hold_id, operation_id::text AS operation_id,
                tenant_type, tenant_id, released_at::text AS released_at, released_by
             FROM ' . $this->nativeSchema->retentionHoldsTable() . ' WHERE hold_id = :hold_id',
            ['hold_id' => $holdId],
        );
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<array{
     *     logicalRecord:array<string,mixed>,
     *     recordedAt:string,
     *     idempotencyKey:string,
     *     recordId:string,
     *     ingressOrdinal:int,
     * }>
     */
    private function logicalRows(): array
    {
        $rows = $this->probe->fetchAllAssociative('SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id,
                ingress_ordinal::text AS ingress_ordinal, idempotency_key_hash, content_hash, protected_body
             FROM '
        . $this->outboxSchema->recordsTable()
        . ' ORDER BY ingress_ordinal, record_id');
        $logical = [];
        foreach ($rows as $row) {
            $body = PostgreSqlBytea::string($row['protected_body'] ?? null);
            $ingress = AuditProtectedIngress::fromArray([
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => $row['tenant_type'] === null
                    ? null
                    : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']],
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'protectedBody' => $body,
            ]);
            $value = $this->protection->unprotectOutbox($ingress)->toArray();
            /** @var array{logicalRecord:array<string,mixed>,recordedAt:string,idempotencyKey:string,recordId:string,ingressOrdinal:int} $value */
            $logical[] = [
                'logicalRecord' => $value['logicalRecord'],
                'recordedAt' => $value['recordedAt'],
                'idempotencyKey' => $value['idempotencyKey'],
                'recordId' => $value['recordId'],
                'ingressOrdinal' => $value['ingressOrdinal'],
            ];
        }

        return $logical;
    }

    /**
     * @param array{
     *     logicalRecord:array<string,mixed>,
     *     recordedAt:string,
     *     idempotencyKey:string,
     *     recordId:string,
     *     ingressOrdinal:int,
     * } $entry
     */
    private function assertLogicalEvent(
        array $entry,
        string $eventType,
        string $outcomeCode,
        RetentionHold $hold,
        ?TenantRef $tenant,
    ): void {
        $logical = $entry['logicalRecord'];
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
            array_keys($logical),
        );
        self::assertSame(
            [
                'type' => $eventType,
                'version' => 1,
                'category' => 'retention',
                'catalogHash' => FrameworkOperationalAuditEventCatalog::catalog()->catalogHash,
            ],
            $logical['event'],
        );
        self::assertSame(
            [
                'id' => (string) new HmacAuditOpaqueIdDeriver(str_repeat('a', 32))->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                    'retention-hold:' . $eventType . ':' . $hold->id()->toString(),
                ),
                'type' => $eventType,
                'phase' => 'committed',
            ],
            $logical['action'],
        );
        self::assertSame(
            [
                'type' => 'operation',
                'id' => (string) new HmacAuditOpaqueIdDeriver(str_repeat('a', 32))->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    $hold->operationId()->toString(),
                ),
            ],
            $logical['resource'],
        );
        self::assertSame(['status' => 'succeeded', 'code' => $outcomeCode], $logical['outcome']);
        self::assertSame(
            [
                'id' => 'blackops.retention.hold',
                'version' => 1,
                'decision' => 'not_applicable',
                'reasonCode' => $outcomeCode,
                'auditDelivery' => 'durable',
            ],
            $logical['policy'],
        );
        self::assertSame(
            $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
            $logical['tenant'],
        );
        self::assertSame(
            [
                'owner' => 'framework',
                'component' => 'blackops.retention.hold',
                'runtime' => 'worker',
                'buildId' => self::BUILD_ID,
            ],
            $logical['source'],
        );
        self::assertSame(
            [
                'occurredAt' => self::WRITER_TIME,
                'recordedAt' => self::STORE_TIME,
            ],
            $logical['time'],
        );
        self::assertSame(self::STORE_TIME, $entry['recordedAt']);
        self::assertSame('retention-hold:' . $logical['action']['id'], $entry['idempotencyKey']);
        self::assertSame(
            [
                'principal' => ['type' => 'operator', 'id' => 'bound-authorized'],
                'execution' => ['type' => 'retention', 'id' => 'execution-canary'],
            ],
            $logical['actor'],
        );
        self::assertSame(['correlationId' => 'correlation-canary'], $logical['correlation']);
        self::assertSame(['version' => 1], $logical['evidence']);
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }

    private const array AUDIT_IDS = [
        '019fca20-0000-7000-8000-000000000301',
        '019fca20-0000-7000-8000-000000000302',
        '019fca20-0000-7000-8000-000000000303',
        '019fca20-0000-7000-8000-000000000304',
        '019fca20-0000-7000-8000-000000000305',
        '019fca20-0000-7000-8000-000000000306',
        '019fca20-0000-7000-8000-000000000307',
        '019fca20-0000-7000-8000-000000000308',
        '019fca20-0000-7000-8000-000000000309',
        '019fca20-0000-7000-8000-000000000310',
    ];

    private const array AUDIT_IDS_LATE = [
        '019fca20-0000-7000-8000-000000000401',
        '019fca20-0000-7000-8000-000000000402',
        '019fca20-0000-7000-8000-000000000403',
    ];
}

/** @mago-expect lint:single-class-per-file */
final class D5NativeClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly DateTimeImmutable $value,
        private readonly ?Throwable $failure = null,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5WriterClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly DateTimeImmutable $value,
        private readonly ?Throwable $failure = null,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->value;
    }

    public function failureMessage(): ?string
    {
        return $this->failure?->getMessage();
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D5StoreClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $value,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5HoldIdGenerator implements PostgreSqlRetentionHoldIdGenerator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
        private readonly ?Throwable $failure = null,
    ) {}

    public function generate(DateTimeImmutable $time): RetentionHoldId
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $value = array_shift($this->ids);
        if (!is_string($value)) {
            throw new \RuntimeException('retention hold ID sequence exhausted.');
        }

        return RetentionHoldId::fromString($value);
    }

    /** @return list<string> */
    public function remaining(): array
    {
        return array_values($this->ids);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5StoreUuidGenerator implements StoreUuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        ++$this->calls;
        $value = array_shift($this->ids);
        if (!is_string($value)) {
            throw new \RuntimeException('audit UUID sequence exhausted.');
        }

        return $value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5AuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'd5h-audit-nonce-' . $this->calls, true), 0, 24);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5AuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey(
            $purpose === AuditProtectionPurpose::AuditRecord ? 'd5h-record' : 'd5h-outbox',
            str_repeat($purpose === AuditProtectionPurpose::AuditRecord ? 'r' : 'o', 32),
        );
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey($keyId, str_repeat($purpose === AuditProtectionPurpose::AuditRecord ? 'r' : 'o', 32));
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5SubjectFaultConnection extends Connection
{
    /** @var array<string,mixed>|false|null */
    private array|false|null $subjectResult = null;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly string $holdsTable,
    ) {
        parent::__construct($params, $driver);
    }

    /** @param array<string,mixed>|false $result */
    public function armSubjectResult(array|false $result): void
    {
        $this->subjectResult = $result;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        if (
            $this->subjectResult !== null
            && str_contains($query, 'SELECT operation_id::text AS operation_id')
            && str_contains($query, $this->holdsTable)
        ) {
            $result = $this->subjectResult;
            $this->subjectResult = null;

            return $result;
        }

        return parent::fetchAssociative($query, $params, $types);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5CountingConnection extends Connection
{
    public int $executeQueryCalls = 0;

    public int $executeStatementCalls = 0;

    public int $fetchAssociativeCalls = 0;

    public int $fetchOneCalls = 0;

    public int $fetchAllAssociativeCalls = 0;

    public function resetSqlCounters(): void
    {
        $this->executeQueryCalls = 0;
        $this->executeStatementCalls = 0;
        $this->fetchAssociativeCalls = 0;
        $this->fetchOneCalls = 0;
        $this->fetchAllAssociativeCalls = 0;
    }

    public function sqlCalls(): int
    {
        return (
            $this->executeStatementCalls
            + $this->executeQueryCalls
            + $this->fetchAssociativeCalls
            + $this->fetchOneCalls
            + $this->fetchAllAssociativeCalls
        );
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null,
    ): \Doctrine\DBAL\Result {
        ++$this->executeQueryCalls;

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        ++$this->executeStatementCalls;

        return parent::executeStatement($sql, $params, $types);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        ++$this->fetchAssociativeCalls;

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        ++$this->fetchOneCalls;

        return parent::fetchOne($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        ++$this->fetchAllAssociativeCalls;

        return parent::fetchAllAssociative($query, $params, $types);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5TransactionObservationConnection extends Connection
{
    public bool $observed = false;

    private bool $observeNextAppend = false;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly Connection $probe,
        private readonly string $outboxTable,
    ) {
        parent::__construct($params, $driver);
    }

    public function armObservation(): void
    {
        $this->observeNextAppend = true;
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $result = parent::fetchOne($query, $params, $types);
        if (
            !$this->observeNextAppend
            || !str_contains(strtoupper($query), 'INSERT INTO')
            || !str_contains(strtoupper($query), strtoupper($this->outboxTable))
        ) {
            return $result;
        }

        $this->observeNextAppend = false;
        if (!$this->isTransactionActive() || !$this->isAutoCommit() || $this->getTransactionNestingLevel() !== 1) {
            throw new \RuntimeException('writer-root-visibility-canary');
        }
        if ((int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->outboxTable) !== 0) {
            throw new \RuntimeException('writer-external-visibility-canary');
        }
        $this->observed = true;

        return $result;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5CommitThenThrowingConnection extends Connection
{
    private bool $throwAfterCommit = false;

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function arm(): void
    {
        $this->throwAfterCommit = true;
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->throwAfterCommit) {
            $this->throwAfterCommit = false;
            throw new \RuntimeException('commit response lost after durable commit.');
        }
    }

    public function rollBack(): void
    {
        if (!$this->isTransactionActive()) {
            return;
        }

        parent::rollBack();
    }
}
