<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeResult;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKey;
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
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Retention\RetentionPurgeAuditWriter;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Idempotency\IdempotencyResultSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\PostgreSqlIdempotencyStore;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\Retention\AuditedRetentionPurgeAuditPort;
use BlackOps\Internal\Retention\AuditedRetentionPurgeService;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlIdempotencySchema;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\AbstractLogger;
use Throwable;

/** @mago-expect lint:too-many-methods */
final class PostgreSqlAuditedRetentionPurgeServiceTest extends TestCase
{
    private const string SCHEMA = 'blackops_retention_purge_audit';
    private const string PAYLOAD = '019fca20-0000-7000-8000-000000000001';
    private const string PAYLOAD_SECONDARY = '019fca20-0000-7000-8000-000000000006';
    private const string PAYLOAD_GLOBAL = '019fca20-0000-7000-8000-000000000007';
    private const string DEAD = '019fca20-0000-7000-8000-000000000002';
    private const string OUTCOME = '019fca20-0000-7000-8000-000000000003';
    private const string INLINE = '019fca20-0000-7000-8000-000000000004';
    private const string IDEMPOTENCY = '019fca20-0000-7000-8000-000000000005';
    private const string INLINE_SECONDARY = '019fca20-0000-7000-8000-000000000008';
    private const string TENANT_TYPE = 'account';
    private const string TENANT_ID = 'tenant-purge-canary';
    private const string BUILD_ID = 'd5p-native-build';
    private const string NATIVE_TIME = '2026-09-25T12:00:00.000000Z';
    private const string WRITER_TIME = '2026-09-25T12:00:01.000000Z';
    private const string STORE_TIME = '2026-09-25T12:00:02.000000Z';

    private Connection $admin;
    private Connection $connection;
    private Connection $probe;
    private PostgreSqlDeferredOperationSchema $nativeSchema;
    private PostgreSqlAuditSchema $recordSchema;
    private PostgreSqlAuditOutboxSchema $outboxSchema;
    private D5PNativeClock $nativeClock;
    private D5PPurgeIdGenerator $nativeIds;
    private D5PWriterClock $writerClock;
    private D5PRecordingLogger $logger;
    private RetentionPurgeAuditWriter $writer;
    private AuditProtectedBodyCodec $protection;
    private AuditedRetentionPurgeService $service;

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
            ...new PostgreSqlIdempotencySchema(self::SCHEMA)->statements(),
            ...$this->recordSchema->statements(),
            ...$this->outboxSchema->statements(),
        ] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $this->nativeClock = new D5PNativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5PPurgeIdGenerator([
            '019fca20-0000-7000-8000-000000000101',
            '019fca20-0000-7000-8000-000000000102',
            '019fca20-0000-7000-8000-000000000103',
            '019fca20-0000-7000-8000-000000000104',
            '019fca20-0000-7000-8000-000000000105',
        ]);
        $this->writerClock = new D5PWriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $this->logger = new D5PRecordingLogger();
        $this->service = $this->service($this->connection);
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

    public function testPurgeAuditsAllFiveTargetsAndUsesPersistedTransportTenant(): void
    {
        $this->seedAllTargets();

        $result = $this->service->purge(
            $this->policy(),
            RetentionPolicyRef::fromString('retention-policy-canary'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-09-25T12:00:00Z'),
        );

        self::assertInstanceOf(RetentionPurgeResult::class, $result);
        self::assertSame(1, $result->transportPayloadsPurged());
        self::assertSame(1, $result->deadLettersDeleted());
        self::assertSame(1, $result->outcomesDeleted());
        self::assertSame(2, $result->journalsDeleted());
        self::assertSame(1, $result->idempotencyRecordsDeleted());
        self::assertSame(6, $result->totalAffected());
        self::assertSame(5, $this->auditCount());
        self::assertSame(5, $this->outboxCount());
        self::assertNull($this->payload(self::PAYLOAD));
        self::assertTrue($this->operationExists(self::PAYLOAD));
        self::assertFalse($this->rowExists('dead_letters', self::DEAD));
        self::assertFalse($this->rowExists('outcomes', self::OUTCOME));
        self::assertFalse($this->rowExists('journal', self::INLINE));
        self::assertFalse($this->rowExists('idempotency_records', self::IDEMPOTENCY));

        $transport = $this->probe->fetchAssociative(
            'SELECT tenant_type, tenant_id FROM '
            . $this->nativeSchema->retentionPurgeAuditsTable()
            . ' WHERE operation_id = :operation_id AND target = :target',
            ['operation_id' => self::PAYLOAD, 'target' => 'transport_payload'],
        );
        self::assertIsArray($transport);
        self::assertSame(self::TENANT_TYPE, $transport['tenant_type']);
        self::assertSame(self::TENANT_ID, $transport['tenant_id']);
        $this->assertLogicalEventsAndLegacyLog();
        self::assertSame(5, $this->nativeIds->calls);
        self::assertSame(5, $this->nativeClock->calls);
        self::assertSame(5, $this->writerClock->calls);
    }

    public function testGlobalTransportPayloadRetainsNullTenantBinding(): void
    {
        $this->sender()->enqueue($this->message(self::PAYLOAD_GLOBAL, null));
        $this->markTerminal(self::PAYLOAD_GLOBAL, 'completed');

        $result = $this->service->purge(
            $this->policy(),
            RetentionPolicyRef::fromString('global-transport-policy'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-09-25T12:00:00Z'),
        );

        self::assertSame(1, $result->transportPayloadsPurged());
        self::assertSame(1, $result->totalAffected());
        self::assertNull($this->payload(self::PAYLOAD_GLOBAL));
        self::assertSame(1, $this->auditCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(1, $this->logger->calls);
        $this->assertAuditTargets([
            ['target' => 'transport_payload', 'operation_id' => self::PAYLOAD_GLOBAL, 'affected_count' => 1],
        ]);
        $this->assertCommittedC3Entries([
            [
                'target' => 'transport_payload',
                'operation_id' => self::PAYLOAD_GLOBAL,
                'affected_count' => 1,
                'tenant' => null,
            ],
        ]);
    }

    public function testNoEffectDoesNotReadWriterClock(): void
    {
        $result = $this->service->purge(
            $this->policy(),
            RetentionPolicyRef::fromString('no-effect-policy'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-09-25T12:00:00Z'),
        );

        self::assertSame(0, $result->totalAffected());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->logger->calls);
        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testHoldAndCutoffLeaveNativeRowsAndAuditCountersUntouched(): void
    {
        $this->seedPayload();
        $this->seedPayload(self::PAYLOAD_SECONDARY);
        $this->connection->executeStatement(
            'INSERT INTO '
            . $this->nativeSchema->retentionHoldsTable()
            . ' (
                hold_id, operation_id, category, reason, placed_at, placed_by
            ) VALUES (:hold_id, :operation_id, :category, :reason, :placed_at, :placed_by)',
            [
                'hold_id' => '019fca20-0000-7000-8000-000000000601',
                'operation_id' => self::PAYLOAD,
                'category' => 'legal',
                'reason' => 'hold-canary',
                'placed_at' => '2026-09-20 00:00:00+00:00',
                'placed_by' => 'fixture',
            ],
        );
        $this->connection->executeStatement(
            'UPDATE '
            . $this->nativeSchema->operationsTable()
            . ' SET updated_at = :updated_at WHERE operation_id = :operation_id',
            [
                'updated_at' => '2026-09-25 11:30:00+00:00',
                'operation_id' => self::PAYLOAD_SECONDARY,
            ],
        );

        $result = $this->service->purge(
            $this->policy(),
            RetentionPolicyRef::fromString('hold-cutoff-policy'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-09-25T12:00:00Z'),
        );

        self::assertSame(0, $result->totalAffected());
        self::assertNotNull($this->payload(self::PAYLOAD));
        self::assertNotNull($this->payload(self::PAYLOAD_SECONDARY));
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->logger->calls);
        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testPlannerRaceCountsAttemptedNoopClockButDoesNotAllocateItsAuditId(): void
    {
        $this->seedPayload();
        $this->seedPayload(self::PAYLOAD_SECONDARY);
        $connection = $this->replaceConnectionWithPlanRaceProbe();
        $connection->armPlanRace();

        $result = $this->service->purge(
            $this->policy(),
            RetentionPolicyRef::fromString('planner-race-policy'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-09-25T12:00:00Z'),
        );

        self::assertSame(1, $result->transportPayloadsPurged());
        self::assertSame(1, $result->totalAffected());
        self::assertNull($this->payload(self::PAYLOAD));
        self::assertNull($this->payload(self::PAYLOAD_SECONDARY));
        self::assertSame(2, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(1, $this->logger->calls);
        self::assertSame(1, $this->auditCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(
            self::PAYLOAD_SECONDARY,
            $this->probe->fetchOne(
                'SELECT operation_id::text FROM ' . $this->nativeSchema->retentionPurgeAuditsTable(),
            ),
        );
    }

    public function testAdmissionRejectsNonAutocommitNestedAndWrongConnectionBeforeWork(): void
    {
        $connection = $this->replaceConnectionWithSqlCountingProbe();
        $connection->setAutoCommit(false);
        $connection->sqlCalls = 0;
        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('non-autocommit-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Non-autocommit connection was accepted.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        } finally {
            $this->restoreConnection($connection);
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertSame(0, $connection->sqlCalls);

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->sqlCalls = 0;
        $failure = null;
        $sqlCallsBeforeRestore = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('nested-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Nested transaction was accepted.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        } finally {
            $sqlCallsBeforeRestore = $connection->sqlCalls;
            $this->restoreConnection($connection);
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertSame(0, $sqlCallsBeforeRestore);

        $wrongService = $this->service($connection, $this->probe);
        $connection->sqlCalls = 0;
        $failure = null;
        try {
            $wrongService->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('wrong-connection-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Writer bound to another connection was accepted.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertSame(0, $connection->sqlCalls);
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testFacadeAndHookPublicPathsAreValueFreeAndCorruptSnapshotsFailClosed(): void
    {
        $surface =
            print_r($this->service, true)
            . var_export($this->service, true)
            . serialize($this->service)
            . json_encode($this->service, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::SCHEMA, $surface);
        self::assertStringNotContainsString('d5p-native-build', $surface);
        self::assertSame([], $this->service->__debugInfo());
        self::assertSame([], $this->service->__serialize());

        $failure = null;
        try {
            unserialize(serialize($this->service), ['allowed_classes' => true]);
            self::fail('Facade unserialization was accepted.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        try {
            clone $this->service;
            self::fail('Facade cloning was accepted.');
        } catch (DeferredTransportException $caught) {
            self::assertNull($caught->getPrevious());
        }

        $serviceReflection = new \ReflectionClass(AuditedRetentionPurgeService::class);
        $corruptService = $serviceReflection->newInstanceWithoutConstructor();
        $serviceReflection->getProperty('snapshot')->setValue($corruptService, new \SensitiveParameterValue([
            'connection' => $this->connection,
        ]));
        try {
            $corruptService->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('corrupt-service-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Corrupt facade snapshot was accepted.');
        } catch (DeferredTransportException $caught) {
            self::assertNull($caught->getPrevious());
        }

        $hook = new AuditedRetentionPurgeAuditPort($this->connection, $this->writer, $this->logger, self::SCHEMA);
        self::assertSame([], $hook->__debugInfo());
        self::assertSame([], $hook->__serialize());
        $hookSurface = print_r($hook, true) . var_export($hook, true) . serialize($hook);
        self::assertStringNotContainsString(self::SCHEMA, $hookSurface);
        try {
            unserialize(serialize($hook), ['allowed_classes' => true]);
            self::fail('Hook unserialization was accepted.');
        } catch (DeferredTransportException $caught) {
            self::assertNull($caught->getPrevious());
        }
        try {
            clone $hook;
            self::fail('Hook cloning was accepted.');
        } catch (DeferredTransportException $caught) {
            self::assertNull($caught->getPrevious());
        }

        $hookReflection = new \ReflectionClass(AuditedRetentionPurgeAuditPort::class);
        $corruptHook = $hookReflection->newInstanceWithoutConstructor();
        $hookReflection->getProperty('snapshot')->setValue($corruptHook, new \SensitiveParameterValue([
            'connection' => $this->connection,
        ]));
        try {
            $corruptHook->record(
                new RetentionPurgeAuditRecord(
                    RetentionPurgeAuditId::fromString('019fca20-0000-7000-8000-000000000701'),
                    OperationId::fromString(self::PAYLOAD),
                    RetentionPurgeTarget::Outcome,
                    1,
                    RetentionPolicyRef::fromString('corrupt-hook-policy'),
                    new DateTimeImmutable('2026-09-25T12:00:00Z'),
                    RetentionActorRef::fromString('system:retention'),
                ),
            );
            self::fail('Corrupt hook snapshot was accepted.');
        } catch (DeferredTransportException $caught) {
            self::assertNull($caught->getPrevious());
        }
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testOwnedFailureTracesHideArgumentsWithTraceArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertIsString($previous);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));

        try {
            $this->seedPayload();
            $this->nativeClock = new D5PNativeClock(
                new DateTimeImmutable(self::NATIVE_TIME),
                new \RuntimeException('trace-facade-source-canary'),
            );
            $this->service = $this->service($this->connection);
            $failure = null;
            try {
                $this->service->purge(
                    $this->policy(),
                    RetentionPolicyRef::fromString('trace-facade-policy-canary'),
                    RetentionActorRef::fromString('trace-facade-actor-canary'),
                    new DateTimeImmutable('2026-09-25T12:00:00Z'),
                );
                self::fail('Facade source failure was swallowed.');
            } catch (DeferredTransportException $caught) {
                $failure = $caught;
            }

            self::assertInstanceOf(DeferredTransportException::class, $failure);
            self::assertNull($failure->getPrevious());
            $this->assertOwnedTraceIsProtected(
                $failure,
                [AuditedRetentionPurgeService::class],
                [
                    'trace-facade-policy-canary',
                    'trace-facade-actor-canary',
                    'trace-facade-source-canary',
                ],
            );

            $this->writerClock = new D5PWriterClock(
                new DateTimeImmutable(self::WRITER_TIME),
                new \RuntimeException('trace-hook-source-canary'),
            );
            $this->service = $this->service($this->connection);
            $hook = new AuditedRetentionPurgeAuditPort($this->connection, $this->writer, $this->logger, self::SCHEMA);
            $this->connection->beginTransaction();
            $hookFailure = null;
            $hookOperationId = '019fca20-0000-7000-8000-000000000702';
            try {
                $hook->record(
                    new RetentionPurgeAuditRecord(
                        RetentionPurgeAuditId::fromString('019fca20-0000-7000-8000-000000000701'),
                        OperationId::fromString($hookOperationId),
                        RetentionPurgeTarget::Outcome,
                        1,
                        RetentionPolicyRef::fromString('trace-hook-policy-canary'),
                        new DateTimeImmutable('2026-09-25T12:00:00Z'),
                        RetentionActorRef::fromString('trace-hook-actor-canary'),
                        new TenantRef('trace-hook-tenant-type-canary', 'trace-hook-tenant-id-canary'),
                    ),
                );
                self::fail('Hook source failure was swallowed.');
            } catch (DeferredTransportException $caught) {
                $hookFailure = $caught;
            } finally {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();
                }
            }

            self::assertInstanceOf(DeferredTransportException::class, $hookFailure);
            self::assertNull($hookFailure->getPrevious());
            $this->assertOwnedTraceIsProtected(
                $hookFailure,
                [AuditedRetentionPurgeAuditPort::class, RetentionPurgeAuditWriter::class],
                [
                    'trace-hook-policy-canary',
                    'trace-hook-actor-canary',
                    'trace-hook-tenant-type-canary',
                    'trace-hook-tenant-id-canary',
                    $hookOperationId,
                    'trace-hook-source-canary',
                ],
            );
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
        }

        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
    }

    public function testLoggerFailureRollsBackCurrentCategoryAfterEarlierCommit(): void
    {
        $this->seedPayload();
        $this->seedInlineJournal();
        $this->seedInlineJournal(self::INLINE_SECONDARY);
        $this->logger->failAt = 3;

        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('logger-failure-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Logger failure was not surfaced.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertNull($this->payload(self::PAYLOAD));
        self::assertTrue($this->rowExists('journal', self::INLINE));
        self::assertTrue($this->rowExists('journal', self::INLINE_SECONDARY));
        self::assertSame(1, $this->auditCount());
        self::assertSame(1, $this->outboxCount());
        self::assertSame(3, $this->nativeClock->calls);
        self::assertSame(3, $this->nativeIds->calls);
        self::assertSame(3, $this->writerClock->calls);
        self::assertSame(3, $this->logger->calls);
        self::assertCount(3, $this->logger->records);
        self::assertSame(
            ['transport_payload', 'journal', 'journal'],
            array_map(static fn(array $entry): mixed => $entry['context']['data']['target'], $this->logger->records),
        );
        self::assertSame(
            [self::PAYLOAD, self::INLINE, self::INLINE_SECONDARY],
            array_map(
                static fn(array $entry): mixed => $entry['context']['data']['operation_id'],
                $this->logger->records,
            ),
        );
        $this->assertAuditTargets([
            ['target' => 'transport_payload', 'operation_id' => self::PAYLOAD, 'affected_count' => 1],
        ]);
    }

    public function testDeliveryFailureRollsBackMutationBeforeLegacyRecord(): void
    {
        $this->seedPayload();
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->recordsTable() . ' CASCADE');
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->partitionStateTable() . ' CASCADE');

        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('delivery-failure-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Delivery failure was not surfaced.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertNotNull($this->payload(self::PAYLOAD));
        self::assertSame(0, $this->auditCount());
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(0, $this->logger->calls);
    }

    public function testLegacyInsertFailureRollsBackCurrentMutationAndDurableAudit(): void
    {
        $this->seedPayload();
        $this->admin->executeStatement('DROP TABLE ' . $this->nativeSchema->retentionPurgeAuditsTable() . ' CASCADE');

        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('legacy-insert-failure-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Legacy INSERT failure was not surfaced.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertNotNull($this->payload(self::PAYLOAD));
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(1, $this->writerClock->calls);
        self::assertSame(0, $this->logger->calls);
        self::assertSame(0, $this->outboxCount());
    }

    public function testNativeIdFailureRollsBackBeforeWriterClock(): void
    {
        $this->seedPayload();
        $this->nativeIds = new D5PPurgeIdGenerator(
            [
                '019fca20-0000-7000-8000-000000000131',
            ],
            new \RuntimeException('native-id-failure-canary'),
        );
        $this->service = $this->service($this->connection);

        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('native-id-failure-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Native ID failure was not surfaced.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertNotNull($this->payload(self::PAYLOAD));
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(1, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testNativeClockFailureRollsBackBeforeNativeIdAndWriterClock(): void
    {
        $this->seedPayload();
        $this->nativeClock = new D5PNativeClock(
            new DateTimeImmutable(self::NATIVE_TIME),
            new \RuntimeException('native-clock-failure-canary'),
        );
        $this->service = $this->service($this->connection);

        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('native-clock-failure-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Native clock failure was not surfaced.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertNotNull($this->payload(self::PAYLOAD));
        self::assertSame(1, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testAdmissionRejectsAmbientTransactionBeforePlannerAndNativeWork(): void
    {
        $this->connection->beginTransaction();
        $failure = null;
        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('admission-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('Ambient transaction was accepted.');
        } catch (DeferredTransportException $caught) {
            $failure = $caught;
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }

        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertNull($failure->getPrevious());
        self::assertSame(0, $this->nativeClock->calls);
        self::assertSame(0, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testMalformedTransportTenantFailsBeforeWriterAndLegacyPersistence(): void
    {
        $this->seedPayload();
        $connection = $this->replaceConnectionWithSubjectProbe();
        foreach ([
            false,
            ['operation_id' => self::PAYLOAD, 'tenant_type' => self::TENANT_TYPE, 'tenant_id' => null],
            [
                'operation_id' => '019fca20-0000-7000-8000-000000000099',
                'tenant_type' => self::TENANT_TYPE,
                'tenant_id' => self::TENANT_ID,
            ],
            ['operation_id' => self::PAYLOAD, 'tenant_type' => ' ' . self::TENANT_TYPE, 'tenant_id' => self::TENANT_ID],
            [
                'operation_id' => self::PAYLOAD,
                'tenant_type' => self::TENANT_TYPE,
                'tenant_id' => self::TENANT_ID . "\x01",
            ],
            ['operation_id' => self::PAYLOAD, 'tenant_type' => self::TENANT_TYPE, 'tenant_id' => "tenant-e\u{0301}"],
            ['operation_id' => self::PAYLOAD, 'tenant_type' => self::TENANT_TYPE, 'tenant_id' => str_repeat('x', 257)],
        ] as $row) {
            $connection->armSubjectResult($row);
            $failure = null;
            try {
                $this->service->purge(
                    $this->policy(),
                    RetentionPolicyRef::fromString('malformed-policy'),
                    RetentionActorRef::fromString('system:retention'),
                    new DateTimeImmutable('2026-09-25T12:00:00Z'),
                );
                self::fail('Malformed transport tenant was accepted.');
            } catch (DeferredTransportException $caught) {
                $failure = $caught;
            }
            self::assertInstanceOf(DeferredTransportException::class, $failure);
            self::assertNull($failure->getPrevious());
            self::assertNotNull($this->payload(self::PAYLOAD));
        }

        self::assertSame(0, $this->auditCount());
        self::assertSame(0, $this->outboxCount());
        self::assertSame(7, $this->nativeClock->calls);
        self::assertSame(7, $this->nativeIds->calls);
        self::assertSame(0, $this->writerClock->calls);
    }

    public function testManualDeadLetterCommitThenThrowPersistsEarlierAndCurrentRootsOnly(): void
    {
        $connection = $this->replaceConnectionWithCommitProbe(2);
        $this->seedAllTargets();
        $connection->arm();

        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('manual-commit-throw-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('The manual root commit failure was not surfaced.');
        } catch (DeferredTransportException $failure) {
            self::assertNull($failure->getPrevious());
        }

        self::assertNull($this->payload(self::PAYLOAD));
        self::assertFalse($this->rowExists('dead_letters', self::DEAD));
        self::assertTrue($this->rowExists('outcomes', self::OUTCOME));
        self::assertTrue($this->rowExists('journal', self::INLINE));
        self::assertTrue($this->rowExists('idempotency_records', self::IDEMPOTENCY));
        self::assertSame(2, $this->auditCount());
        self::assertSame(2, $this->outboxCount());
        self::assertSame(2, $this->nativeClock->calls);
        self::assertSame(2, $this->nativeIds->calls);
        self::assertSame(2, $this->writerClock->calls);
        self::assertSame(2, $this->logger->calls);
        $this->assertAuditTargets([
            ['target' => 'transport_payload', 'operation_id' => self::PAYLOAD, 'affected_count' => 1],
            ['target' => 'dead_letter', 'operation_id' => self::DEAD, 'affected_count' => 1],
        ]);
        self::assertSame(2, $connection->commitCalls());
        self::assertSame(1, $connection->rollbackCalls());
        $this->assertCommittedC3Entries([
            [
                'target' => 'transport_payload',
                'operation_id' => self::PAYLOAD,
                'affected_count' => 1,
                'tenant' => $this->tenant(),
            ],
            [
                'target' => 'dead_letter',
                'operation_id' => self::DEAD,
                'affected_count' => 1,
                'tenant' => $this->tenant(),
            ],
        ]);
    }

    public function testDbalOutcomeCommitThenThrowPersistsEarlierAndCurrentRootsOnly(): void
    {
        $connection = $this->replaceConnectionWithCommitProbe(3);
        $this->seedAllTargets();
        $connection->arm();

        try {
            $this->service->purge(
                $this->policy(),
                RetentionPolicyRef::fromString('dbal-commit-throw-policy'),
                RetentionActorRef::fromString('system:retention'),
                new DateTimeImmutable('2026-09-25T12:00:00Z'),
            );
            self::fail('The DBAL root commit failure was not surfaced.');
        } catch (DeferredTransportException $failure) {
            self::assertNull($failure->getPrevious());
        }

        self::assertNull($this->payload(self::PAYLOAD));
        self::assertFalse($this->rowExists('dead_letters', self::DEAD));
        self::assertFalse($this->rowExists('outcomes', self::OUTCOME));
        self::assertTrue($this->rowExists('journal', self::INLINE));
        self::assertTrue($this->rowExists('idempotency_records', self::IDEMPOTENCY));
        self::assertSame(3, $this->auditCount());
        self::assertSame(3, $this->outboxCount());
        self::assertSame(3, $this->nativeClock->calls);
        self::assertSame(3, $this->nativeIds->calls);
        self::assertSame(3, $this->writerClock->calls);
        self::assertSame(3, $this->logger->calls);
        $this->assertAuditTargets([
            ['target' => 'transport_payload', 'operation_id' => self::PAYLOAD, 'affected_count' => 1],
            ['target' => 'dead_letter', 'operation_id' => self::DEAD, 'affected_count' => 1],
            ['target' => 'outcome', 'operation_id' => self::OUTCOME, 'affected_count' => 1],
        ]);
        self::assertSame(3, $connection->commitCalls());
        self::assertSame(1, $connection->rollbackCalls());
        $this->assertCommittedC3Entries([
            [
                'target' => 'transport_payload',
                'operation_id' => self::PAYLOAD,
                'affected_count' => 1,
                'tenant' => $this->tenant(),
            ],
            [
                'target' => 'dead_letter',
                'operation_id' => self::DEAD,
                'affected_count' => 1,
                'tenant' => $this->tenant(),
            ],
            [
                'target' => 'outcome',
                'operation_id' => self::OUTCOME,
                'affected_count' => 1,
                'tenant' => $this->tenant(),
            ],
        ]);
    }

    private function service(Connection $connection, ?Connection $writerConnection = null): AuditedRetentionPurgeService
    {
        $bundle = $this->auditBundle($writerConnection ?? $connection);
        $this->writer = new RetentionPurgeAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $this->writerClock,
            new ActorContext(
                new ActorRef('origin-canary', 'service'),
                new ActorRef('bound-authorized', 'operator'),
                new ActorRef('execution-canary', 'retention'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => 'correlation-canary']),
            AuditSourceRuntime::Worker,
            self::BUILD_ID,
        );

        return new AuditedRetentionPurgeService(
            $connection,
            $this->writer,
            $this->logger,
            self::SCHEMA,
            $this->nativeClock,
            $this->nativeIds,
        );
    }

    private function replaceConnectionWithCommitProbe(int $throwAt): D5PCommitThenThrowingConnection
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5PCommitThenThrowingConnection($params, $driver, $throwAt);
        $this->nativeClock = new D5PNativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5PPurgeIdGenerator([
            '019fca20-0000-7000-8000-000000000111',
            '019fca20-0000-7000-8000-000000000112',
            '019fca20-0000-7000-8000-000000000113',
            '019fca20-0000-7000-8000-000000000114',
            '019fca20-0000-7000-8000-000000000115',
        ]);
        $this->writerClock = new D5PWriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $this->logger = new D5PRecordingLogger();
        $this->service = $this->service($this->connection);

        return $this->connection;
    }

    private function replaceConnectionWithSubjectProbe(): D5PSubjectFaultConnection
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5PSubjectFaultConnection($params, $driver);
        $this->nativeClock = new D5PNativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5PPurgeIdGenerator([
            '019fca20-0000-7000-8000-000000000121',
            '019fca20-0000-7000-8000-000000000122',
            '019fca20-0000-7000-8000-000000000123',
            '019fca20-0000-7000-8000-000000000124',
            '019fca20-0000-7000-8000-000000000125',
            '019fca20-0000-7000-8000-000000000126',
            '019fca20-0000-7000-8000-000000000127',
        ]);
        $this->writerClock = new D5PWriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $this->logger = new D5PRecordingLogger();
        $this->service = $this->service($this->connection);

        return $this->connection;
    }

    private function replaceConnectionWithPlanRaceProbe(): D5PPlanRaceConnection
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5PPlanRaceConnection(
            $params,
            $driver,
            $this->probe,
            $this->nativeSchema->operationsTable(),
            self::PAYLOAD,
        );
        $this->nativeClock = new D5PNativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5PPurgeIdGenerator([
            '019fca20-0000-7000-8000-000000000141',
        ]);
        $this->writerClock = new D5PWriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $this->logger = new D5PRecordingLogger();
        $this->service = $this->service($this->connection);

        return $this->connection;
    }

    private function replaceConnectionWithSqlCountingProbe(): D5PSqlCountingConnection
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D5PSqlCountingConnection($params, $driver);
        $this->nativeClock = new D5PNativeClock(new DateTimeImmutable(self::NATIVE_TIME));
        $this->nativeIds = new D5PPurgeIdGenerator([
            '019fca20-0000-7000-8000-000000000151',
            '019fca20-0000-7000-8000-000000000152',
        ]);
        $this->writerClock = new D5PWriterClock(new DateTimeImmutable(self::WRITER_TIME));
        $this->logger = new D5PRecordingLogger();
        $this->service = $this->service($this->connection);

        return $this->connection;
    }

    private function seedAllTargets(): void
    {
        $this->seedPayload();
        $this->seedDeadLetter();
        $this->seedOutcome();
        $this->seedInlineJournal();
        $this->seedIdempotency();
    }

    private function seedPayload(string $operationId = self::PAYLOAD): void
    {
        $this->sender()->enqueue($this->message($operationId, $this->tenant()));
        $this->markTerminal($operationId, 'completed');
    }

    private function seedDeadLetter(): void
    {
        $this->sender()->enqueue($this->message(self::DEAD, $this->tenant()));
        $this->markTerminal(self::DEAD, 'dead_lettered');
        $this->tombstonePayload(self::DEAD);
        $this->connection->executeStatement(
            'INSERT INTO '
            . $this->nativeSchema->deadLettersTable()
            . ' (operation_id, tenant_type, tenant_id, encoded_reason, moved_at)'
            . ' VALUES (:operation_id, :tenant_type, :tenant_id, decode(\'424f5044\', \'hex\'), :moved_at)',
            [
                'operation_id' => self::DEAD,
                'tenant_type' => self::TENANT_TYPE,
                'tenant_id' => self::TENANT_ID,
                'moved_at' => '2026-09-20 00:00:00+00:00',
            ],
        );
    }

    private function seedOutcome(): void
    {
        $this->sender()->enqueue($this->message(self::OUTCOME, $this->tenant()));
        $this->markTerminal(self::OUTCOME, 'completed');
        $this->tombstonePayload(self::OUTCOME);
        $this->connection->executeStatement(
            'INSERT INTO '
            . $this->nativeSchema->outcomesTable()
            . ' (operation_id, tenant_type, tenant_id, outcome_type, schema_version, encoded_payload, completed_at)'
            . ' VALUES (:operation_id, :tenant_type, :tenant_id, :outcome_type, 1, :payload, :completed_at)',
            [
                'operation_id' => self::OUTCOME,
                'tenant_type' => self::TENANT_TYPE,
                'tenant_id' => self::TENANT_ID,
                'outcome_type' => 'retention.test',
                'payload' => PostgreSqlTestStorageProtection::outcomeEnvelope(
                    '{}',
                    self::OUTCOME,
                    'retention.test',
                    1,
                    $this->tenant(),
                ),
                'completed_at' => '2026-09-20 00:00:00+00:00',
            ],
            ['payload' => \Doctrine\DBAL\ParameterType::BINARY],
        );
    }

    private function seedInlineJournal(string $operationId = self::INLINE): void
    {
        $this->sender()->enqueue($this->message($operationId, $this->tenant()));
        $this->connection->executeStatement(
            'DELETE FROM ' . $this->nativeSchema->operationsTable() . ' WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );
        $journal = new PostgreSqlJournalSchema(self::SCHEMA)->journalTable();
        $recordBase = $operationId === self::INLINE ? 700 : 800;
        foreach ([1, 2] as $sequence) {
            $recordId = sprintf('019fca20-0000-7000-8000-%012d', $recordBase + $sequence);
            $this->connection->executeStatement(
                'INSERT INTO ' . $journal . ' (
                    record_id, operation_id, operation_type, tenant_type, tenant_id, sequence, event, schema_version,
                    operation_schema_version, occurred_at, encoded_record
                ) VALUES (
                    :record_id, :operation_id, :operation_type, :tenant_type, :tenant_id, :sequence, :event, 1,
                    1, :occurred_at, :encoded_record
                )',
                [
                    'record_id' => $recordId,
                    'operation_id' => $operationId,
                    'operation_type' => 'retention.inline',
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => self::TENANT_ID,
                    'sequence' => $sequence,
                    'event' => 'operation.received',
                    'occurred_at' => '2026-09-20 00:00:0' . $sequence . '+00:00',
                    'encoded_record' => PostgreSqlTestStorageProtection::journalEnvelope(
                        '{}',
                        $recordId,
                        $operationId,
                        'operation.received',
                        1,
                        $this->tenant(),
                    ),
                ],
                ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
    }

    private function seedIdempotency(): void
    {
        $store = new PostgreSqlIdempotencyStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $key = new IdempotencyKey('d5p-idempotency-key');
        $scope = new IdempotencyScopeHasher()->hash(
            'retention.operation',
            new ActorRef('fixture-user', 'user'),
            $key,
            $this->tenant(),
        );
        $value = new class implements OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('retention.operation', $value);
        $created = new DateTimeImmutable('2026-09-20T00:00:00Z');
        $claim = $store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            OperationId::fromString(self::IDEMPOTENCY),
            new Inline(),
            $created,
            $created->modify('+1 day'),
            'retention.operation',
            1,
            $this->tenant(),
        );
        self::assertInstanceOf(ProcessingRecord::class, $claim->record());
        self::assertTrue($store->terminalize(
            OperationId::fromString(self::IDEMPOTENCY),
            new TerminalRecord(
                $claim->record()->scope(),
                $claim->record()->key(),
                $claim->record()->fingerprint(),
                OperationId::fromString(self::IDEMPOTENCY),
                new Inline(),
                $created,
                $created->modify('+1 day'),
                null,
                new IdempotencyResultSnapshot(OperationResult::rejected(
                    RejectionReason::conflict('fixture'),
                    OperationId::fromString(self::IDEMPOTENCY),
                )),
            ),
        ));
    }

    private function markTerminal(string $operationId, string $state): void
    {
        $this->connection->executeStatement(
            'UPDATE '
            . $this->nativeSchema->operationsTable()
            . ' SET state = :state, updated_at = :updated_at WHERE operation_id = :operation_id',
            [
                'state' => $state,
                'updated_at' => '2026-09-20 00:00:00+00:00',
                'operation_id' => $operationId,
            ],
        );
    }

    private function tombstonePayload(string $operationId): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . $this->nativeSchema->operationsTable() . ' SET encoded_payload = NULL, encoded_context = NULL,
                payload_purged_at = :purged_at, updated_at = :updated_at
             WHERE operation_id = :operation_id',
            [
                'purged_at' => '2026-09-20 00:00:00+00:00',
                'updated_at' => '2026-09-20 00:00:00+00:00',
                'operation_id' => $operationId,
            ],
        );
    }

    private function policy(): RetentionPolicy
    {
        return new RetentionPolicy(
            RetentionPeriod::days(1),
            RetentionPeriod::days(1),
            RetentionPeriod::days(1),
            RetentionPeriod::days(1),
            RetentionPeriod::days(1),
        );
    }

    private function sender(): PostgreSqlDeferredOperationSender
    {
        return new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            new DateTimeImmutable('2026-09-20T00:00:00Z'),
        );
    }

    private function message(string $operationId, ?TenantRef $tenant): \BlackOps\Core\Execution\DeferredOperationMessage
    {
        return new \BlackOps\Core\Execution\DeferredOperationMessage(
            OperationId::fromString($operationId),
            'retention.test',
            1,
            '{"payload":"retention-input"}',
            '{"context":"retention-context"}',
            new DateTimeImmutable('2026-09-20T00:00:00Z'),
            $tenant,
            new ActorRef('legacy-origin', 'worker'),
        );
    }

    private function tenant(): TenantRef
    {
        return new TenantRef(self::TENANT_TYPE, self::TENANT_ID);
    }

    private function payload(string $operationId): mixed
    {
        return $this->probe->fetchOne(
            'SELECT encoded_payload FROM '
            . $this->nativeSchema->operationsTable()
            . ' WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );
    }

    private function operationExists(string $operationId): bool
    {
        return (bool) $this->probe->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM '
            . $this->nativeSchema->operationsTable()
            . ' WHERE operation_id = :operation_id)',
            ['operation_id' => $operationId],
        );
    }

    private function rowExists(string $table, string $operationId): bool
    {
        $tableName = match ($table) {
            'dead_letters' => $this->nativeSchema->deadLettersTable(),
            'outcomes' => $this->nativeSchema->outcomesTable(),
            'journal' => new PostgreSqlJournalSchema(self::SCHEMA)->journalTable(),
            'idempotency_records' => new PostgreSqlIdempotencySchema(self::SCHEMA)->table(),
            default => throw new \LogicException(),
        };

        return (bool) $this->probe->fetchOne('SELECT EXISTS (SELECT 1 FROM '
        . $tableName
        . ' WHERE operation_id = :operation_id)', ['operation_id' => $operationId]);
    }

    private function auditCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->nativeSchema->retentionPurgeAuditsTable());
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->outboxSchema->recordsTable());
    }

    /** @param list<array{target:string,operation_id:string,affected_count:int}> $expected */
    private function assertAuditTargets(array $expected): void
    {
        $rows = $this->probe->fetchAllAssociative('SELECT target, operation_id::text AS operation_id, affected_count
             FROM '
        . $this->nativeSchema->retentionPurgeAuditsTable()
        . ' ORDER BY audit_id');
        self::assertSame($expected, array_map(static fn(array $row): array => [
            'target' => $row['target'],
            'operation_id' => $row['operation_id'],
            'affected_count' => (int) $row['affected_count'],
        ], $rows));
    }

    /**
     * @param list<class-string> $classes
     * @param list<string> $canaries
     */
    private function assertOwnedTraceIsProtected(Throwable $failure, array $classes, array $canaries): void
    {
        $ownedFrames = array_values(array_filter($failure->getTrace(), static fn(array $frame): bool => in_array(
            $frame['class'] ?? null,
            $classes,
            true,
        )));
        self::assertNotEmpty($ownedFrames);
        $protectedArguments = [];
        foreach ($ownedFrames as $frame) {
            foreach ($frame['args'] ?? [] as $argument) {
                self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
                $protectedArguments[] = $argument;
            }
        }
        self::assertNotEmpty($protectedArguments);
        $ownedTrace = print_r($ownedFrames, true) . var_export($ownedFrames, true);
        self::assertStringContainsString('SensitiveParameterValue', $ownedTrace);
        foreach ($canaries as $canary) {
            self::assertStringNotContainsString($canary, $ownedTrace);
            self::assertStringNotContainsString($canary, $failure->getTraceAsString());
        }
    }

    /**
     * @param list<array{target:string,operation_id:string,affected_count:int,tenant:?TenantRef}> $expected
     */
    private function assertCommittedC3Entries(array $expected): void
    {
        $logicalRows = $this->logicalRows();
        $legacyRows = $this->probe->fetchAllAssociative('SELECT audit_id::text AS audit_id, operation_id::text AS operation_id,
                tenant_type, tenant_id, target, affected_count
             FROM '
        . $this->nativeSchema->retentionPurgeAuditsTable()
        . ' ORDER BY audit_id');
        self::assertCount(count($expected), $logicalRows);
        self::assertCount(count($expected), $legacyRows);

        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $expectedByTarget = [];
        foreach ($expected as $entry) {
            $expectedByTarget[$entry['target'] . ':' . $entry['operation_id']] = $entry;
        }
        $logicalByResource = [];
        foreach ($logicalRows as $logical) {
            $resource = $logical['logicalRecord']['resource'] ?? null;
            self::assertIsArray($resource);
            $resourceId = $resource['id'] ?? null;
            self::assertIsString($resourceId);
            self::assertArrayNotHasKey($resourceId, $logicalByResource);
            $logicalByResource[$resourceId] = $logical;
        }

        foreach ($legacyRows as $legacy) {
            $target = (string) $legacy['target'];
            $operationId = (string) $legacy['operation_id'];
            $entry = $expectedByTarget[$target . ':' . $operationId] ?? null;
            self::assertIsArray($entry);
            $resourceId = (string) $deriver->derive(
                AuditOpaqueIdPurpose::Resource,
                'retention-purge:' . $target . ':' . $operationId,
            );
            $logical = $logicalByResource[$resourceId] ?? null;
            self::assertIsArray($logical);
            $record = $logical['logicalRecord'];
            $actionId = (string) $deriver->derive(
                AuditOpaqueIdPurpose::Action,
                'retention-purge:retention.purge.committed:' . $legacy['audit_id'],
            );
            $tenant = $entry['tenant'];

            self::assertSame($entry['target'], $legacy['target']);
            self::assertSame($entry['operation_id'], $legacy['operation_id']);
            self::assertSame($entry['affected_count'], (int) $legacy['affected_count']);
            self::assertSame($tenant?->type(), $legacy['tenant_type']);
            self::assertSame($tenant?->id(), $legacy['tenant_id']);
            self::assertSame(
                [
                    'id' => $actionId,
                    'type' => 'retention.purge.committed',
                    'phase' => 'committed',
                ],
                $record['action'],
            );
            self::assertSame(
                [
                    'type' => 'operation_data',
                    'id' => $resourceId,
                ],
                $record['resource'],
            );
            self::assertSame(
                $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
                $record['tenant'],
            );
            self::assertSame(
                $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
                $logical['tenantBinding'],
            );
            self::assertSame('retention-purge:' . $actionId, $logical['idempotencyKey']);
        }
    }

    /**
     * @return list<array{
     *     logicalRecord:array<string,mixed>,
     *     tenantBinding:array{type:string,id:string}|null,
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
        . ' ORDER BY record_id');
        $logical = [];
        foreach ($rows as $row) {
            $tenantBinding = $row['tenant_type'] === null
                ? null
                : ['type' => (string) $row['tenant_type'], 'id' => (string) $row['tenant_id']];
            $ingress = AuditProtectedIngress::fromArray([
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => $tenantBinding,
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'protectedBody' => PostgreSqlBytea::string($row['protected_body'] ?? null),
            ]);
            $value = $this->protection->unprotectOutbox($ingress)->toArray();
            /** @var array{logicalRecord:array<string,mixed>,recordedAt:string,idempotencyKey:string,recordId:string,ingressOrdinal:int} $value */
            $logical[] = [
                'logicalRecord' => $value['logicalRecord'],
                'tenantBinding' => $tenantBinding,
                'recordedAt' => $value['recordedAt'],
                'idempotencyKey' => $value['idempotencyKey'],
                'recordId' => $value['recordId'],
                'ingressOrdinal' => $value['ingressOrdinal'],
            ];
        }

        return $logical;
    }

    private function assertLogicalEventsAndLegacyLog(): void
    {
        $logicalRows = $this->logicalRows();
        self::assertCount(5, $logicalRows);
        $legacyRows = $this->probe->fetchAllAssociative('SELECT audit_id::text AS audit_id, operation_id::text AS operation_id,
                tenant_type, tenant_id, target, affected_count, policy,
                to_char(purged_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS purged_at,
                purged_by
             FROM '
        . $this->nativeSchema->retentionPurgeAuditsTable()
        . ' ORDER BY audit_id');
        self::assertCount(5, $legacyRows);
        self::assertSame(
            ['transport_payload', 'dead_letter', 'outcome', 'journal', 'idempotency_record'],
            array_map(static fn(array $row): string => (string) $row['target'], $legacyRows),
        );
        $expectedTargets = [
            'transport_payload' => ['operationId' => self::PAYLOAD, 'count' => 1, 'tenant' => $this->tenant()],
            'journal' => ['operationId' => self::INLINE, 'count' => 2, 'tenant' => $this->tenant()],
            'outcome' => ['operationId' => self::OUTCOME, 'count' => 1, 'tenant' => $this->tenant()],
            'dead_letter' => ['operationId' => self::DEAD, 'count' => 1, 'tenant' => $this->tenant()],
            'idempotency_record' => ['operationId' => self::IDEMPOTENCY, 'count' => 1, 'tenant' => $this->tenant()],
        ];

        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $logicalByResource = [];
        foreach ($logicalRows as $logical) {
            $resource = $logical['logicalRecord']['resource'] ?? null;
            self::assertIsArray($resource);
            $resourceId = $resource['id'] ?? null;
            self::assertIsString($resourceId);
            self::assertArrayNotHasKey($resourceId, $logicalByResource);
            $logicalByResource[$resourceId] = $logical;
        }

        foreach ($legacyRows as $legacy) {
            $target = (string) $legacy['target'];
            $expected = $expectedTargets[$target] ?? null;
            self::assertIsArray($expected);
            $resourceId = (string) $deriver->derive(
                AuditOpaqueIdPurpose::Resource,
                'retention-purge:' . $target . ':' . $legacy['operation_id'],
            );
            $entry = $logicalByResource[$resourceId] ?? null;
            self::assertIsArray($entry);
            $logical = $entry['logicalRecord'];
            self::assertSame($entry['recordId'], $logical['recordId']);
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
                    'type' => 'retention.purge.committed',
                    'version' => 1,
                    'category' => 'retention',
                    'catalogHash' => FrameworkOperationalAuditEventCatalog::catalog()->catalogHash,
                ],
                $logical['event'],
            );
            self::assertSame(
                [
                    'id' => (string) $deriver->derive(
                        AuditOpaqueIdPurpose::Action,
                        'retention-purge:retention.purge.committed:' . $legacy['audit_id'],
                    ),
                    'type' => 'retention.purge.committed',
                    'phase' => 'committed',
                ],
                $logical['action'],
            );
            self::assertSame(
                [
                    'type' => 'operation_data',
                    'id' => $resourceId,
                ],
                $logical['resource'],
            );
            self::assertSame(['status' => 'succeeded', 'code' => 'retention_purged'], $logical['outcome']);
            self::assertSame(
                [
                    'id' => 'blackops.retention.purge',
                    'version' => 1,
                    'decision' => 'not_applicable',
                    'reasonCode' => 'retention_purged',
                    'auditDelivery' => 'durable',
                ],
                $logical['policy'],
            );
            $tenant = $expected['tenant'];
            self::assertSame(
                $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
                $logical['tenant'],
            );
            self::assertSame(
                $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
                $entry['tenantBinding'],
            );
            self::assertSame(
                [
                    'owner' => 'framework',
                    'component' => 'blackops.retention.purge',
                    'runtime' => 'worker',
                    'buildId' => self::BUILD_ID,
                ],
                $logical['source'],
            );
            self::assertSame(['occurredAt' => self::WRITER_TIME, 'recordedAt' => self::STORE_TIME], $logical['time']);
            self::assertSame(self::STORE_TIME, $entry['recordedAt']);
            self::assertSame('retention-purge:' . $logical['action']['id'], $entry['idempotencyKey']);
            self::assertSame(
                [
                    'principal' => ['type' => 'operator', 'id' => 'bound-authorized'],
                    'execution' => ['type' => 'retention', 'id' => 'execution-canary'],
                ],
                $logical['actor'],
            );
            self::assertSame(['correlationId' => 'correlation-canary'], $logical['correlation']);
            self::assertSame(['version' => 1], $logical['evidence']);
            self::assertStringNotContainsString('retention-policy-canary', json_encode($logical, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('system:retention', json_encode($logical, JSON_THROW_ON_ERROR));
            self::assertSame($expected['operationId'], $legacy['operation_id']);
            self::assertSame($expected['count'], (int) $legacy['affected_count']);
            self::assertSame('retention-policy-canary', $legacy['policy']);
            self::assertSame('system:retention', $legacy['purged_by']);
            self::assertSame(self::NATIVE_TIME, $legacy['purged_at']);
            self::assertSame($tenant?->type(), $legacy['tenant_type']);
            self::assertSame($tenant?->id(), $legacy['tenant_id']);
        }

        self::assertCount(5, $this->logger->records);
        $legacyByTarget = [];
        foreach ($legacyRows as $legacy) {
            $legacyByTarget[$legacy['target'] . ':' . $legacy['operation_id']] = $legacy;
        }
        foreach ($this->logger->records as $entry) {
            self::assertSame('info', $entry['level']);
            self::assertSame('Retention purge audit recorded.', $entry['message']);
            self::assertSame(1, $entry['context']['schemaVersion']);
            self::assertSame('audit', $entry['context']['kind']);
            self::assertSame('retention.purge.completed', $entry['context']['event']);
            $data = $entry['context']['data'];
            $legacy = $legacyByTarget[$data['target'] . ':' . $data['operation_id']] ?? null;
            self::assertIsArray($legacy);
            self::assertSame($legacy['audit_id'], $data['audit_id']);
            self::assertSame($legacy['operation_id'], $data['operation_id']);
            self::assertSame($legacy['target'], $data['target']);
            self::assertSame((int) $legacy['affected_count'], $data['affected_count']);
            self::assertSame('retention-policy-canary', $data['policy']);
            self::assertSame(self::NATIVE_TIME, $data['purged_at']);
            self::assertSame(['id' => '[masked]', 'type' => 'retention'], $data['purged_by']);
            $tenant = $expectedTargets[$data['target']]['tenant'];
            self::assertSame(
                $tenant === null ? null : ['id' => '[masked]', 'type' => $tenant->type()],
                $data['tenant'],
            );
        }
    }

    /** @return array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator} */
    private function auditBundle(Connection $connection): array
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
            new D5PKeySource(),
            new D5PNonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $ingress,
        );
        $storeClock = new D5PStoreClock(new DateTimeImmutable(self::STORE_TIME));
        $uuids = new D5PStoreUuidGenerator($this->storeIds());
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

        $this->protection = $protection;

        return [
            'selector' => $selector,
            'delivery' => new AuditDeliveryCoordinator($connection, $selector, $recordStore, $outboxStore),
        ];
    }

    /** @return list<string> */
    private function storeIds(): array
    {
        $ids = [];
        for ($index = 1; $index <= 40; ++$index) {
            $ids[] = sprintf('019fca20-0000-7%03x-8000-%012x', $index, $index + 1000);
        }

        return $ids;
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

    private function restoreConnection(Connection $connection): void
    {
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        if (!$connection->isAutoCommit()) {
            $connection->setAutoCommit(true);
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PNativeClock implements ClockInterface
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
final class D5PWriterClock implements ClockInterface
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
final class D5PPurgeIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
        private readonly ?Throwable $failure = null,
    ) {}

    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $value = array_shift($this->ids);
        if (!is_string($value)) {
            throw new \RuntimeException('purge id sequence exhausted');
        }

        return RetentionPurgeAuditId::fromString($value);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D5PStoreClock implements ClockInterface
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
final class D5PStoreUuidGenerator implements \BlackOps\Internal\Identifier\Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        $value = array_shift($this->ids);
        if (!is_string($value)) {
            throw new \RuntimeException('audit UUID sequence exhausted');
        }

        return $value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PCommitThenThrowingConnection extends Connection
{
    private int $commits = 0;

    private int $rollbackCalls = 0;

    private bool $armed = false;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly int $throwAt,
    ) {
        parent::__construct($params, $driver);
    }

    public function arm(): void
    {
        $this->armed = true;
    }

    public function commitCalls(): int
    {
        return $this->commits;
    }

    public function rollbackCalls(): int
    {
        return $this->rollbackCalls;
    }

    public function commit(): void
    {
        parent::commit();
        ++$this->commits;
        if ($this->armed && $this->commits === $this->throwAt) {
            $this->armed = false;
            throw new \RuntimeException('commit response lost after durable commit.');
        }
    }

    public function rollBack(): void
    {
        ++$this->rollbackCalls;
        parent::rollBack();
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PSubjectFaultConnection extends Connection
{
    /** @var array<string,mixed>|false|null */
    private array|false|null $subjectResult = null;

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
            && str_contains($query, 'operations')
        ) {
            $result = $this->subjectResult;
            $this->subjectResult = null;

            return $result;
        }

        return parent::fetchAssociative($query, $params, $types);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PPlanRaceConnection extends Connection
{
    private bool $armed = false;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly Connection $racer,
        private readonly string $operationsTable,
        private readonly string $operationId,
    ) {
        parent::__construct($params, $driver);
    }

    public function armPlanRace(): void
    {
        $this->armed = true;
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $rows = parent::fetchAllAssociative($query, $params, $types);
        if ($this->armed && str_contains($query, 'encoded_payload') && str_contains($query, $this->operationsTable)) {
            $this->armed = false;
            $this->racer->executeStatement(
                'UPDATE ' . $this->operationsTable . '
                 SET encoded_payload = NULL, encoded_context = NULL,
                     payload_purged_at = :purged_at, updated_at = :updated_at
                 WHERE operation_id = :operation_id',
                [
                    'purged_at' => '2026-09-25 11:59:00+00:00',
                    'updated_at' => '2026-09-25 11:59:00+00:00',
                    'operation_id' => $this->operationId,
                ],
            );
        }

        return $rows;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PSqlCountingConnection extends Connection
{
    public int $sqlCalls = 0;

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        ++$this->sqlCalls;

        return parent::executeStatement($sql, $params, $types);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        ++$this->sqlCalls;

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        ++$this->sqlCalls;

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        ++$this->sqlCalls;

        return parent::fetchOne($query, $params, $types);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PRecordingLogger extends AbstractLogger
{
    public int $calls = 0;

    public ?int $failAt = null;

    /** @var list<array{level:mixed,message:string,context:array}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        ++$this->calls;
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
        if ($this->failAt !== null && $this->calls === $this->failAt) {
            throw new \RuntimeException('logger failure canary');
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class D5PNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'd5p-audit-nonce-' . $this->calls, true), 0, 24);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D5PKeySource implements \BlackOps\Internal\Audit\Protection\AuditProtectionKeySource
{
    public function activeKey(
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey(
            $purpose === \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose::AuditRecord
                ? 'd5p-record'
                : 'd5p-outbox',
            str_repeat(
                $purpose === \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose::AuditRecord ? 'r' : 'o',
                32,
            ),
        );
    }

    public function key(
        string $keyId,
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey($keyId, str_repeat(
            $purpose === \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose::AuditRecord ? 'r' : 'o',
            32,
        ));
    }
}
