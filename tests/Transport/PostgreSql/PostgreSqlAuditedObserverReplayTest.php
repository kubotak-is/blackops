<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHook;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriter;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\Replay\ObserverReplayRequest;
use BlackOps\Internal\Replay\ObserverReplayResult;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use BlackOps\Internal\Replay\ObserverReplayTargetRegistry;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 */
final class PostgreSqlAuditedObserverReplayTest extends TestCase
{
    private const string SCHEMA = 'blackops_replay_audit';
    private const string OPERATION = '019fca20-0000-7000-8000-000000000001';
    private const string RECORD_ONE = '019fca20-0000-7000-8000-000000000011';
    private const string RECORD_TWO = '019fca20-0000-7000-8000-000000000012';
    private const string RECORD_THREE = '019fca20-0000-7000-8000-000000000013';
    private const string TENANT_TYPE = 'account';
    private const string TENANT_ID = 'tenant-a';
    private const string TENANT_B_ID = 'tenant-b';
    private const string AUDIT_ID = '0123456789abcdef0123456789abcdef';
    private const string CANARY = 'observer-replay-postgres-private-canary';

    private Connection $admin;
    private Connection $connection;
    private Connection $probe;
    private PostgreSqlAuditSchema $auditSchema;
    private PostgreSqlAuditOutboxSchema $outboxSchema;
    private AuditProtectedBodyCodec $auditProtection;
    private ObserverReplayAuditClock $writerClock;
    private ObserverReplayRuntime $runtime;
    private ObserverReplayRecordingObserver $observer;

    /** @var list<Connection> */
    private array $faultConnections = [];

    protected function setUp(): void
    {
        $this->admin = $this->connection();
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        $this->admin->executeStatement('CREATE SCHEMA "' . self::SCHEMA . '"');
        $this->auditSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        $this->outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema(self::SCHEMA));
        foreach ([
            ...new PostgreSqlJournalSchema(self::SCHEMA)->statements(),
            ...$this->auditSchema->statements(),
            ...$this->outboxSchema->statements(),
        ] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $bundle = $this->auditBundle($this->connection);
        $this->auditProtection = $bundle['protection'];
        $this->writerClock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $writer = new ObserverReplayAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $this->writerClock,
            new ActorContext(
                null,
                new ActorRef('authorized-operator', 'operator'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => '019fca20-0000-7000-8000-000000000099']),
            'observer-replay-audit-test',
        );
        $store = new PostgreSqlObserverReplayStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            hook: new ObserverReplayAuditHook($writer),
        );
        $this->observer = new ObserverReplayRecordingObserver();
        $this->runtime = new ObserverReplayRuntime(
            $store,
            new ObserverReplayTargetRegistry([
                new \BlackOps\Internal\Journal\JournalObserverBinding('recording', $this->observer),
            ]),
            new \BlackOps\Internal\Projection\ObservedJournalRecordProjector(
                new \BlackOps\Internal\Projection\SensitiveProjectionFilter(),
            ),
            100,
        );
    }

    protected function tearDown(): void
    {
        foreach ([
            ...$this->faultConnections,
            $this->connection ?? null,
            $this->probe ?? null,
            $this->admin ?? null,
        ] as $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            try {
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
            }
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
        try {
            $cleanup = $this->connection();
            $cleanup->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
            $cleanup->close();
        } catch (Throwable) {
        }
    }

    public function testAllFourPhaseEventsPreserveSelectedTenantAndIndependentIngressRows(): void
    {
        $this->insertRecords([
            $this->record(
                self::RECORD_ONE,
                1,
                $this->tenant(),
                new ActorContext(
                    new ActorRef('historical-origin', 'origin'),
                    new ActorRef('historical-authorization', 'historical'),
                    new ActorRef('historical-execution', 'historical'),
                ),
                '019fca20-0000-7000-8000-000000000098',
            ),
            $this->record(self::RECORD_TWO, 2, null),
        ]);

        $result = $this->runtime->replay($this->request(
            'all-phases',
            PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
        ));

        self::assertInstanceOf(ObserverReplayResult::class, $result);
        self::assertTrue($result->complete);
        self::assertSame(2, $result->selected);
        self::assertSame(2, $result->delivered);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $this->observer->recordIds);
        self::assertSame(4, $this->writerClock->calls);

        $checkpoint = $this->probe->fetchAssociative('SELECT state, selected_count, delivered_count, cursor_record_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'all-phases']);
        self::assertIsArray($checkpoint);
        self::assertSame('complete', $checkpoint['state']);
        self::assertSame(2, (int) $checkpoint['selected_count']);
        self::assertSame(2, (int) $checkpoint['delivered_count']);
        self::assertSame('2|' . self::RECORD_TWO, $checkpoint['cursor_record_id']);

        $audit = $this->probe->fetchAllAssociative('SELECT state, selected_count, delivered_count FROM "'
        . self::SCHEMA
        . '"."observer_replay_audits" WHERE checkpoint_id=:id ORDER BY started_at', ['id' => 'all-phases']);
        self::assertCount(1, $audit);
        self::assertSame('complete', $audit[0]['state']);
        self::assertSame(2, (int) $audit[0]['selected_count']);
        self::assertSame(2, (int) $audit[0]['delivered_count']);

        $events = $this->logicalOutbox();
        self::assertCount(4, $events);
        self::assertSame(
            [
                'replay.observer.progressed',
                'replay.observer.progressed',
                'replay.observer.started',
                'replay.observer.succeeded',
            ],
            $this->eventTypes($events),
        );
        foreach ($events as $event) {
            self::assertSame('replay', $event['event']['category']);
            self::assertSame(
                FrameworkOperationalAuditEventCatalog::catalog()->catalogHash,
                $event['event']['catalogHash'],
            );
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/D', (string) $event['action']['id']);
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/D', (string) $event['resource']['id']);
            self::assertSame('durable', $event['policy']['auditDelivery']);
            self::assertSame(
                [
                    'owner' => 'framework',
                    'component' => 'blackops.replay.observer',
                    'runtime' => 'replay',
                    'buildId' => 'observer-replay-audit-test',
                ],
                $event['source'],
            );
            self::assertSame(
                [
                    'principal' => ['type' => 'operator', 'id' => 'authorized-operator'],
                    'execution' => ['type' => 'worker', 'id' => 'execution-1'],
                ],
                $event['actor'],
            );
            self::assertSame(['correlationId' => '019fca20-0000-7000-8000-000000000099'], $event['correlation']);
            self::assertSame(['version' => 1], $event['evidence']);
            $encoded = json_encode($event, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('legacy-cli-actor', $encoded);
            self::assertStringNotContainsString('legacy-cli-reason', $encoded);
            self::assertStringNotContainsString('historical-', $encoded);
            self::assertStringNotContainsString('000000000098', $encoded);
        }
        $resourceIds = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $progress = [];
        foreach ($events as $event) {
            if ($event['event']['type'] === 'replay.observer.progressed') {
                $progress[$event['resource']['id']] = $event['tenant'];
            } else {
                self::assertNull($event['tenant']);
            }
        }
        self::assertSame(
            [
                (string) $resourceIds->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    'observer-replay:record:' . self::RECORD_ONE,
                ) => ['type' => self::TENANT_TYPE, 'id' => self::TENANT_ID],
                (string) $resourceIds->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    'observer-replay:record:' . self::RECORD_TWO,
                ) => null,
            ],
            $progress,
        );
        self::assertSame(
            4,
            (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"'),
        );
    }

    public function testPausedAndDryRunDoNotReadTheWriterClockOrCreateAuditIngress(): void
    {
        $this->insertRecords([
            $this->record(self::RECORD_ONE, 1, $this->tenant()),
            $this->record(self::RECORD_TWO, 2, null),
        ]);
        $before = $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"');

        $dryRun = $this->runtime->dryRun(
            PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(self::RECORD_ONE)),
            ['recording'],
        );
        self::assertSame(1, $dryRun->selected);
        self::assertSame(
            (int) $before,
            (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"'),
        );
        self::assertSame(0, $this->writerClock->calls);

        $this->runtime->replay($this->request(
            'paused-phase',
            PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            batchSize: 1,
        ));
        self::assertSame(2, $this->writerClock->calls);
        self::assertSame(
            2,
            (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"'),
        );
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started'],
            $this->eventTypes($this->logicalOutbox()),
        );
        self::assertSame('paused', $this->probe->fetchOne('SELECT state FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'paused-phase']));
        $pausedAudit = $this->auditRows('paused-phase');
        self::assertCount(1, $pausedAudit);
        self::assertSame('complete', $pausedAudit[0]['state']);
        self::assertSame(1, (int) $pausedAudit[0]['selected_count']);
        self::assertSame(1, (int) $pausedAudit[0]['delivered_count']);
    }

    public function testAllSelectorsPersistTheirNativeCursorRendering(): void
    {
        $this->insertRecords([
            $this->record(self::RECORD_ONE, 1, $this->tenant()),
            $this->record(self::RECORD_TWO, 2, null),
        ]);

        $cases = [
            [
                'checkpoint' => 'selector-record',
                'selector' => PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(self::RECORD_ONE)),
                'cursor' => self::RECORD_ONE,
            ],
            [
                'checkpoint' => 'selector-operation',
                'selector' => PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                'cursor' => '2|' . self::RECORD_TWO,
            ],
            [
                'checkpoint' => 'selector-time',
                'selector' => PostgreSqlObserverReplaySelector::time(
                    new DateTimeImmutable('2026-09-29T12:00:00Z'),
                    new DateTimeImmutable('2026-09-29T12:00:01.500000Z'),
                ),
                'cursor' => '2026-09-29 12:00:01.000000+00:00|' . self::RECORD_ONE,
            ],
        ];
        foreach ($cases as $case) {
            $result = $this->runtime->replay($this->request($case['checkpoint'], $case['selector']));
            self::assertTrue($result->complete);
            self::assertSame($case['cursor'], $this->checkpointRow($case['checkpoint'])['cursor_record_id']);
        }

        self::assertSame(10, $this->outboxCount());
    }

    public function testLegacyProgressRejectsSourceLessAndAmbientRootsBeforeNativeEffects(): void
    {
        $clock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $store = $this->auditedStore($this->connection, $clock);
        $binding = $store->begin(
            new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                'legacy-source-less',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ['recording'],
                'legacy-cli-actor',
                'legacy-cli-reason',
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            ),
        );
        $clockCalls = $clock->calls;

        $failure = null;
        try {
            $store->advance(
                'legacy-source-less',
                null,
                1,
                $binding->auditId,
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            );
            self::fail('source-less legacy progress was accepted');
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertSame('Replay audit progress requires a selected record.', $failure->getMessage());
        self::assertSame($clockCalls, $clock->calls);
        $checkpoint = $this->checkpointRow('legacy-source-less');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertNull($checkpoint['cursor_record_id']);
        self::assertSame(0, (int) $checkpoint['selected_count']);
        self::assertSame(0, (int) $checkpoint['delivered_count']);
        self::assertSame(1, count($this->auditRows('legacy-source-less')));
        self::assertSame(0, (int) $this->auditRows('legacy-source-less')[0]['selected_count']);
        self::assertSame(1, $this->outboxCount());

        $this->connection->beginTransaction();
        $ambient = null;
        try {
            $store->advance(
                'legacy-source-less',
                null,
                1,
                $binding->auditId,
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            );
            self::fail('ambient legacy progress was accepted');
        } catch (\RuntimeException $exception) {
            $ambient = $exception;
        }
        self::assertInstanceOf(\RuntimeException::class, $ambient);
        self::assertSame('Replay mutation requires an idle auto-commit connection.', $ambient->getMessage());
        self::assertSame($clockCalls, $clock->calls);
        $this->connection->rollBack();
        $store->unlock('legacy-source-less');
        self::assertSame(1, $this->outboxCount());
    }

    public function testZeroRowSelectionCompletesWithScopeEventsOnly(): void
    {
        $result = $this->runtime->replay($this->request(
            'zero-row',
            PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
        ));

        self::assertTrue($result->complete);
        self::assertSame(0, $result->selected);
        self::assertSame(0, $result->delivered);
        self::assertSame(2, $this->writerClock->calls);
        self::assertSame(
            ['replay.observer.started', 'replay.observer.succeeded'],
            $this->eventTypes($this->logicalOutbox()),
        );
        self::assertSame('complete', $this->checkpointRow('zero-row')['state']);
        self::assertSame('complete', $this->auditRows('zero-row')[0]['state']);
    }

    public function testInterleavedTenantAThenTenantBThenGlobalUsesEachRawTenant(): void
    {
        $this->insertRecords([
            $this->record(self::RECORD_ONE, 1, $this->tenant()),
            $this->record(self::RECORD_TWO, 2, new TenantRef(self::TENANT_TYPE, self::TENANT_B_ID)),
            $this->record(self::RECORD_THREE, 3, null),
        ]);

        $result = $this->runtime->replay($this->request(
            'interleaved-tenants',
            PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
        ));

        self::assertTrue($result->complete);
        self::assertSame(3, $result->delivered);
        $resourceIds = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $progress = [];
        foreach ($this->logicalOutbox() as $event) {
            if ($event['event']['type'] === 'replay.observer.progressed') {
                $progress[$event['resource']['id']] = $event['tenant'];
            }
        }
        self::assertSame(
            [
                (string) $resourceIds->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    'observer-replay:record:' . self::RECORD_ONE,
                ) => ['type' => self::TENANT_TYPE, 'id' => self::TENANT_ID],
                (string) $resourceIds->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    'observer-replay:record:' . self::RECORD_TWO,
                ) => ['type' => self::TENANT_TYPE, 'id' => self::TENANT_B_ID],
                (string) $resourceIds->derive(
                    \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Resource,
                    'observer-replay:record:' . self::RECORD_THREE,
                ) => null,
            ],
            $progress,
        );
    }

    public function testSelectedRawRecordRetainsTenantAfterSourceMetadataChanges(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $store = $this->auditedStore($this->connection);
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION));
        $binding = $store->begin(
            new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                'source-metadata-change',
                $selector,
                ['recording'],
                'legacy-cli-actor',
                'legacy-cli-reason',
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            ),
        );
        $batch = $store->select($selector, 1, null);
        $source = $batch['records'][0];
        $this->connection->executeStatement('UPDATE "'
        . self::SCHEMA
        . '"."journal" SET tenant_type=:type, tenant_id=:id WHERE record_id=:record', [
            'type' => self::TENANT_TYPE,
            'id' => self::TENANT_B_ID,
            'record' => self::RECORD_ONE,
        ]);
        $store->advanceSelected(
            'source-metadata-change',
            $store->cursorFor($selector, $source),
            $binding->auditId,
            $source,
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
        $store->finishInvocation(
            'source-metadata-change',
            'complete',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
            $binding->auditId,
        );
        $store->unlock('source-metadata-change');

        $progress = array_values(array_filter(
            $this->logicalOutbox(),
            static fn(array $event): bool => $event['event']['type'] === 'replay.observer.progressed',
        ));
        self::assertCount(1, $progress);
        self::assertSame(['type' => self::TENANT_TYPE, 'id' => self::TENANT_ID], $progress[0]['tenant']);
    }

    public function testSelectedRawRecordRetainsTenantAfterSourceDeletion(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $store = $this->auditedStore($this->connection);
        $selector = PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(self::RECORD_ONE));
        $binding = $store->begin(
            new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                'source-deletion',
                $selector,
                ['recording'],
                'legacy-cli-actor',
                'legacy-cli-reason',
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            ),
        );
        $source = $store->select($selector, 1, null)['records'][0];
        $this->connection->executeStatement('DELETE FROM "' . self::SCHEMA . '"."journal" WHERE record_id=:record', [
            'record' => self::RECORD_ONE,
        ]);
        $store->advanceSelected(
            'source-deletion',
            $store->cursorFor($selector, $source),
            $binding->auditId,
            $source,
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
        $store->finishInvocation(
            'source-deletion',
            'complete',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
            $binding->auditId,
        );
        $store->unlock('source-deletion');

        $progress = array_values(array_filter(
            $this->logicalOutbox(),
            static fn(array $event): bool => $event['event']['type'] === 'replay.observer.progressed',
        ));
        self::assertCount(1, $progress);
        self::assertSame(['type' => self::TENANT_TYPE, 'id' => self::TENANT_ID], $progress[0]['tenant']);
        self::assertFalse($this->probe->fetchAssociative('SELECT record_id FROM "'
        . self::SCHEMA
        . '"."journal" WHERE record_id=:record', ['record' => self::RECORD_ONE]));
    }

    public function testStoreAdmissionRejectsForeignNonAutocommitAndNestedRootsBeforeNativeOrC3Work(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION));
        $request = static function (string $checkpoint) use (
            $selector,
        ): \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest {
            return new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                $checkpoint,
                $selector,
                ['recording'],
                'authorized-operator',
                'admission-control',
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            );
        };

        $clock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $foreign = $this->connection();
        $this->faultConnections[] = $foreign;
        $store = $this->auditedStore($this->connection, $clock, $foreign);

        $foreignFailure = null;
        try {
            $store->begin($request('foreign-connection'));
            self::fail('a writer connection mismatch was accepted');
        } catch (AuditDeliveryException $exception) {
            $foreignFailure = $exception;
        }
        self::assertInstanceOf(AuditDeliveryException::class, $foreignFailure);
        self::assertSame(AuditDeliveryFailure::TransactionInvalid, $foreignFailure->failure());
        self::assertNull($foreignFailure->getPrevious());
        self::assertFalse($this->probe->fetchAssociative('SELECT checkpoint_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'foreign-connection']));
        self::assertSame([], $this->auditRows('foreign-connection'));
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $clock->calls);

        $nonAutocommitFailure = null;
        $this->connection->setAutoCommit(false);
        try {
            $store->begin($request('non-autocommit'));
            self::fail('a non-autocommit connection was accepted');
        } catch (\RuntimeException $exception) {
            $nonAutocommitFailure = $exception;
        } finally {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if (!$this->connection->isAutoCommit()) {
                $this->connection->setAutoCommit(true);
            }
        }
        self::assertInstanceOf(\RuntimeException::class, $nonAutocommitFailure);
        self::assertSame(
            'Replay mutation requires an idle auto-commit connection.',
            $nonAutocommitFailure->getMessage(),
        );
        self::assertFalse($this->probe->fetchAssociative('SELECT checkpoint_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'non-autocommit']));
        self::assertSame([], $this->auditRows('non-autocommit'));
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $clock->calls);

        $nestedFailure = null;
        $this->connection->beginTransaction();
        try {
            $store->begin($request('nested-root'));
            self::fail('a nested connection was accepted');
        } catch (\RuntimeException $exception) {
            $nestedFailure = $exception;
        } finally {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
        self::assertInstanceOf(\RuntimeException::class, $nestedFailure);
        self::assertSame('Replay mutation requires an idle auto-commit connection.', $nestedFailure->getMessage());
        self::assertFalse($this->probe->fetchAssociative('SELECT checkpoint_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'nested-root']));
        self::assertSame([], $this->auditRows('nested-root'));
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $clock->calls);
    }

    public function testMalformedSelectedLineageRollsBackNativeMutationAndPreservesRawC3Ingress(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $clock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $store = $this->auditedStore($this->connection, $clock);
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION));
        $binding = $store->begin(
            new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                'malformed-selected-lineage',
                $selector,
                ['recording'],
                'authorized-operator',
                'malformed-selected-lineage',
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            ),
        );
        $source = $store->select($selector, 1, null)['records'][0];
        $beforeIngress = $this->outboxRows();
        self::assertCount(1, $beforeIngress);
        self::assertSame(1, $clock->calls);

        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $failure = null;
        try {
            $store->advanceSelected(
                'malformed-selected-lineage',
                '2|' . self::RECORD_ONE,
                $binding->auditId,
                $source,
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            );
            self::fail('malformed selected lineage was accepted');
        } catch (AuditDeliveryException $exception) {
            $failure = $exception;
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
        self::assertInstanceOf(AuditDeliveryException::class, $failure);
        self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
        self::assertNull($failure->getPrevious());
        $this->assertSensitiveFrame($failure, ObserverReplayAuditHook::class, 'progressed', 5);
        $this->assertSensitiveFrame(
            $failure,
            \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayAdvanceRoot::class,
            'run',
            1,
        );
        $this->assertSensitiveFrame($failure, PostgreSqlObserverReplayStore::class, 'advanceSelected', 5);
        self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());

        $checkpoint = $this->checkpointRow('malformed-selected-lineage');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertNull($checkpoint['cursor_record_id']);
        self::assertSame(0, (int) $checkpoint['selected_count']);
        self::assertSame(0, (int) $checkpoint['delivered_count']);
        $audit = $this->auditRows('malformed-selected-lineage');
        self::assertCount(1, $audit);
        self::assertSame('started', $audit[0]['state']);
        self::assertSame(0, (int) $audit[0]['selected_count']);
        self::assertSame(0, (int) $audit[0]['delivered_count']);
        self::assertSame($beforeIngress, $this->outboxRows());
        self::assertSame(['replay.observer.started'], $this->eventTypes($this->logicalOutbox()));
        self::assertSame(1, $clock->calls);

        $store->advanceSelected(
            'malformed-selected-lineage',
            $store->cursorFor($selector, $source),
            $binding->auditId,
            $source,
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
        $store->finishInvocation(
            'malformed-selected-lineage',
            'complete',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
            $binding->auditId,
        );
        $store->unlock('malformed-selected-lineage');

        self::assertSame(3, $clock->calls);
        self::assertSame($beforeIngress, array_slice($this->outboxRows(), 0, count($beforeIngress)));
        self::assertSame(3, $this->outboxCount());
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started', 'replay.observer.succeeded'],
            $this->eventTypes($this->logicalOutbox()),
        );
        $checkpoint = $this->checkpointRow('malformed-selected-lineage');
        self::assertIsArray($checkpoint);
        self::assertSame('complete', $checkpoint['state']);
        self::assertSame('1|' . self::RECORD_ONE, $checkpoint['cursor_record_id']);
        self::assertSame(1, (int) $checkpoint['selected_count']);
        self::assertSame(1, (int) $checkpoint['delivered_count']);
        $audit = $this->auditRows('malformed-selected-lineage');
        self::assertSame('complete', $audit[0]['state']);
        self::assertSame(1, (int) $audit[0]['selected_count']);
        self::assertSame(1, (int) $audit[0]['delivered_count']);
    }

    public function testRuntimeRequestFrameRedactsSensitiveArgument(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $failure = null;
        try {
            $this->runtime->replay(
                new ObserverReplayRequest(
                    PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                    ['recording'],
                    'Invalid Checkpoint',
                    self::CANARY,
                    self::CANARY,
                    new DateTimeImmutable('2026-09-29T12:00:00Z'),
                ),
            );
            self::fail('an unsafe replay request was accepted');
        } catch (\InvalidArgumentException $exception) {
            $failure = $exception;
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
        self::assertSame('Replay actor, reason, and checkpoint must be non-empty.', $failure->getMessage());
        $this->assertSensitiveFrame($failure, ObserverReplayRuntime::class, 'replay', 1);
        self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
    }

    public function testInstalledStoreAndResumeFramesRedactOwnedArguments(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $foreign = $this->connection();
            $this->faultConnections[] = $foreign;
            $clock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
            $store = $this->auditedStore($this->connection, $clock, $foreign);
            $request = new \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest(
                'privacy-' . self::CANARY,
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ['recording'],
                self::CANARY,
                self::CANARY,
                new DateTimeImmutable('2026-09-29T12:00:00Z'),
            );
            $cases = [
                'begin' => [
                    'call' => static function () use ($store, $request): void {
                        $store->begin($request);
                    },
                    'method' => 'begin',
                    'count' => 1,
                    'helper' => \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRoot::class,
                ],
                'advance' => [
                    'call' => static function () use ($store): void {
                        $store->advance(
                            'privacy-' . self::CANARY,
                            '1|' . self::RECORD_ONE,
                            1,
                            self::AUDIT_ID,
                            new DateTimeImmutable('2026-09-29T12:00:00Z'),
                        );
                    },
                    'method' => 'advance',
                    'count' => 5,
                    'helper' => \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayAdvanceRoot::class,
                ],
                'finish' => [
                    'call' => static function () use ($store): void {
                        $store->finishInvocation(
                            'privacy-' . self::CANARY,
                            'complete',
                            new DateTimeImmutable('2026-09-29T12:00:00Z'),
                            self::AUDIT_ID,
                        );
                    },
                    'method' => 'finishInvocation',
                    'count' => 4,
                ],
                'fail' => [
                    'call' => static function () use ($store): void {
                        $store->fail(
                            'privacy-' . self::CANARY,
                            new \RuntimeException(self::CANARY),
                            new DateTimeImmutable('2026-09-29T12:00:00Z'),
                            self::AUDIT_ID,
                        );
                    },
                    'method' => 'fail',
                    'count' => 4,
                ],
            ];
            foreach ($cases as $name => $case) {
                $failure = null;
                try {
                    $case['call']();
                    self::fail($name . ' accepted a foreign writer connection');
                } catch (AuditDeliveryException $exception) {
                    $failure = $exception;
                }
                self::assertInstanceOf(AuditDeliveryException::class, $failure);
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSensitiveFrame(
                    $failure,
                    PostgreSqlObserverReplayStore::class,
                    $case['method'],
                    $case['count'],
                );
                if (isset($case['helper'])) {
                    $this->assertSensitiveFrame($failure, $case['helper'], 'run', 1);
                }
            }
            self::assertSame(0, $clock->calls);
            self::assertSame(0, $this->outboxCount());
            self::assertSame([], $this->auditRows('privacy-' . self::CANARY));

            $resumeFailure = null;
            try {
                $this->runtime->resume(
                    'resume-' . self::CANARY,
                    self::CANARY,
                    self::CANARY,
                    1,
                    new DateTimeImmutable('2026-09-29T12:00:00Z'),
                );
                self::fail('a missing resume checkpoint was accepted');
            } catch (\InvalidArgumentException $exception) {
                $resumeFailure = $exception;
            }
            self::assertInstanceOf(\InvalidArgumentException::class, $resumeFailure);
            self::assertSame('Replay checkpoint does not exist.', $resumeFailure->getMessage());
            $this->assertSensitiveFrame($resumeFailure, ObserverReplayRuntime::class, 'resume', 5);
            self::assertStringNotContainsString(self::CANARY, print_r(
                array_values(array_filter(
                    $resumeFailure->getTrace(),
                    static fn(array $frame): bool => (
                        ($frame['class'] ?? null) === ObserverReplayRuntime::class
                        && ($frame['function'] ?? null) === 'resume'
                    ),
                )),
                true,
            ));
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testRuntimeOwnedFramesReachBeginDeliveryFinalizationAndRelease(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);

            $beginFault = $this->faultConnection();
            $beginFault->armAppendFaultAt(1);
            $beginFixture = $this->faultRuntime($beginFault, null, 0);
            $beginFailure = null;
            try {
                $beginFixture['runtime']->replay($this->request(
                    'privacy-begin',
                    PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ));
                self::fail('the begin append fault was not reached');
            } catch (AuditDeliveryException $exception) {
                $beginFailure = $exception;
            }
            self::assertInstanceOf(AuditDeliveryException::class, $beginFailure);
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $beginFailure->failure());
            $this->assertSensitiveFrame(
                $beginFailure,
                \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginTransaction::class,
                'run',
                1,
            );

            $deliveryFixture = $this->faultRuntime($this->faultConnection(), new ObserverReplayFailingObserver(), 64);
            $deliveryFailure = null;
            try {
                $deliveryFixture['runtime']->replay($this->request(
                    'privacy-delivery',
                    PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ));
                self::fail('the observer failure was not reached');
            } catch (JournalObservationFailed $exception) {
                $deliveryFailure = $exception;
            }
            self::assertInstanceOf(JournalObservationFailed::class, $deliveryFailure);
            $this->assertSensitiveFrame($deliveryFailure, ObserverReplayRuntime::class, 'deliver', 3);
            $this->assertSensitiveFrame($deliveryFailure, ObserverReplayRuntime::class, 'recordFailure', 4);

            $finalizeFault = $this->faultConnection();
            $finalizeFault->armFinalizeFault();
            $finalizeFixture = $this->faultRuntime($finalizeFault, null, 128);
            $finalizeFailure = null;
            $finalizeReached = false;
            try {
                $finalizeFixture['runtime']->replay($this->request(
                    'privacy-finalize',
                    PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ));
                $finalizeReached = true;
            } catch (\Throwable $exception) {
                $finalizeFailure = $exception;
            }
            self::assertFalse($finalizeReached, 'the finalize fault was not reached');
            self::assertInstanceOf(ObserverReplayFinalizeFault::class, $finalizeFailure);
            self::assertSame('observer replay finalize fault', $finalizeFailure->getMessage());
            $this->assertSensitiveFrame($finalizeFailure, ObserverReplayRuntime::class, 'finalize', 3);

            $releaseFault = $this->faultConnection();
            $releaseFault->armUnlockTrace();
            $releaseFixture = $this->faultRuntime($releaseFault, null, 192);
            $releaseFailure = null;
            $releaseReached = false;
            try {
                $releaseFixture['runtime']->replay($this->request(
                    'privacy-release',
                    PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
                ));
                $releaseReached = true;
            } catch (\Throwable $exception) {
                $releaseFailure = $exception;
            }
            self::assertFalse($releaseReached, 'the release fault was not reached');
            self::assertInstanceOf(ObserverReplayReleaseFault::class, $releaseFailure);
            self::assertSame('observer replay release fault', $releaseFailure->getMessage());
            $this->assertSensitiveFrame($releaseFailure, ObserverReplayRuntime::class, 'release', 1);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testStartedAppendFaultAfterIngressRollsBackNativeAndC3Roots(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armAppendFaultAt(1);
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'fault-started',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (AuditDeliveryException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(AuditDeliveryException::class, $failure);
        self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        self::assertSame('Audit delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertSame(1, $fault->appendAttempts());
        self::assertFalse($this->probe->fetchAssociative('SELECT checkpoint_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => 'fault-started']));
        self::assertSame([], $this->auditRows('fault-started'));
        self::assertSame(0, $this->outboxCount());
        self::assertSame([], $fixture['observer']->recordIds);
    }

    public function testProgressAppendFaultAfterIngressRollsBackOnlyTheCurrentNativeRoot(): void
    {
        $this->insertRecords([
            $this->record(self::RECORD_ONE, 1, $this->tenant()),
            $this->record(self::RECORD_TWO, 2, null),
            $this->record(self::RECORD_THREE, 3, null),
        ]);
        $fault = $this->faultConnection();
        $fault->armAppendFaultAt(3);
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'fault-progressed',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertSame(3, $fault->appendAttempts());
        $checkpoint = $this->checkpointRow('fault-progressed');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertSame('1|' . self::RECORD_ONE, $checkpoint['cursor_record_id']);
        self::assertSame(1, (int) $checkpoint['selected_count']);
        self::assertSame(1, (int) $checkpoint['delivered_count']);
        $oldAudit = $this->auditRows('fault-progressed')[0];
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(1, (int) $oldAudit['selected_count']);
        self::assertSame(1, (int) $oldAudit['delivered_count']);
        self::assertSame(2, $this->outboxCount());
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started'],
            $this->eventTypes($this->logicalOutbox()),
        );
        $beforeIngress = $this->outboxRows();
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $fixture['observer']->recordIds);

        $result = $fixture['runtime']->resume(
            'fault-progressed',
            'legacy-cli-actor',
            'legacy-cli-reason',
            now: new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
        self::assertTrue($result->complete);
        self::assertSame(2, $result->selected);
        self::assertSame(2, $result->delivered);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_TWO, self::RECORD_TWO, self::RECORD_THREE],
            $fixture['observer']->recordIds,
        );
        $audits = $this->auditRows('fault-progressed');
        self::assertCount(2, $audits);
        $oldRows = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] === $oldAudit['audit_id'],
        ));
        $newRows = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ));
        self::assertCount(1, $oldRows);
        self::assertCount(1, $newRows);
        self::assertSame($oldAudit, $oldRows[0]);
        self::assertSame('started', $oldRows[0]['state']);
        self::assertSame('complete', $newRows[0]['state']);
        self::assertSame(2, (int) $newRows[0]['selected_count']);
        self::assertSame(2, (int) $newRows[0]['delivered_count']);
        self::assertSame(7, $fault->appendAttempts());
        $final = $this->checkpointRow('fault-progressed');
        self::assertIsArray($final);
        self::assertSame('complete', $final['state']);
        self::assertSame('3|' . self::RECORD_THREE, $final['cursor_record_id']);
        self::assertSame(3, (int) $final['selected_count']);
        self::assertSame(3, (int) $final['delivered_count']);
        self::assertSame(6, $this->outboxCount());
        self::assertSame($beforeIngress, array_slice($this->outboxRows(), 0, count($beforeIngress)));
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $succeededActions = array_values(array_map(
            static fn(array $event): string => (string) $event['action']['id'],
            array_filter(
                $this->logicalOutbox(),
                static fn(array $event): bool => $event['event']['type'] === 'replay.observer.succeeded',
            ),
        ));
        self::assertSame(
            [(string) $deriver->derive(
                \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                'observer-replay:replay.observer.succeeded:' . $newRows[0]['audit_id'],
            )],
            $succeededActions,
        );
        self::assertNotContains(
            (string) $deriver->derive(
                \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                'observer-replay:replay.observer.succeeded:' . $oldAudit['audit_id'],
            ),
            $succeededActions,
        );
        self::assertSame(
            [
                'replay.observer.progressed',
                'replay.observer.progressed',
                'replay.observer.progressed',
                'replay.observer.started',
                'replay.observer.started',
                'replay.observer.succeeded',
            ],
            $this->eventTypes($this->logicalOutbox()),
        );
    }

    public function testSucceededAppendFaultAfterIngressRollsBackTerminalNativeRoot(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armAppendFaultAt(3);
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'fault-succeeded',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (AuditDeliveryException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(AuditDeliveryException::class, $failure);
        self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        self::assertNull($failure->getPrevious());
        self::assertSame(3, $fault->appendAttempts());
        $checkpoint = $this->checkpointRow('fault-succeeded');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertSame('1|' . self::RECORD_ONE, $checkpoint['cursor_record_id']);
        self::assertSame(1, (int) $checkpoint['selected_count']);
        self::assertSame(1, (int) $checkpoint['delivered_count']);
        self::assertSame('started', $this->auditRows('fault-succeeded')[0]['state']);
        self::assertSame(1, (int) $this->auditRows('fault-succeeded')[0]['selected_count']);
        self::assertSame(1, (int) $this->auditRows('fault-succeeded')[0]['delivered_count']);
        self::assertSame(2, $this->outboxCount());
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started'],
            $this->eventTypes($this->logicalOutbox()),
        );
        self::assertSame([self::RECORD_ONE], $fixture['observer']->recordIds);
    }

    public function testFailedAppendFaultAfterIngressRollsBackFailureRootWithoutRetry(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armAppendFaultAt(2);
        $fixture = $this->faultRuntime($fault, new ObserverReplayFailingObserver());

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'fault-failed',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertSame(2, $fault->appendAttempts());
        $checkpoint = $this->checkpointRow('fault-failed');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertSame('started', $this->auditRows('fault-failed')[0]['state']);
        self::assertSame(1, $this->outboxCount());
        self::assertSame(['replay.observer.started'], $this->eventTypes($this->logicalOutbox()));
        self::assertSame([self::RECORD_ONE], $fixture['observer']->recordIds);
    }

    public function testCommitAfterThrowOnStartedRootLeavesDurableStartedRowsAndUnknownResponse(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armCommitFaultAt(1, 'after');
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'commit-started',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertSame('observer replay commit after', $failure->getMessage());
        self::assertSame(1, $fault->commitCalls());
        $checkpoint = $this->checkpointRow('commit-started');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertSame('started', $this->auditRows('commit-started')[0]['state']);
        self::assertSame(1, $this->outboxCount());
        self::assertSame(['replay.observer.started'], $this->eventTypes($this->logicalOutbox()));
        self::assertSame([], $fixture['observer']->recordIds);
    }

    public function testCommitAfterThrowOnProgressRootPreservesProgressAndSkipsSyntheticFailure(): void
    {
        $this->insertRecords([
            $this->record(self::RECORD_ONE, 1, $this->tenant()),
            $this->record(self::RECORD_TWO, 2, null),
            $this->record(self::RECORD_THREE, 3, null),
        ]);
        $fault = $this->faultConnection();
        $fault->armCommitFaultAt(2, 'after');
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'commit-progressed',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertSame(2, $fault->commitCalls());
        $checkpoint = $this->checkpointRow('commit-progressed');
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
        self::assertSame('1|' . self::RECORD_ONE, $checkpoint['cursor_record_id']);
        self::assertSame(1, (int) $checkpoint['selected_count']);
        self::assertSame(1, (int) $checkpoint['delivered_count']);
        $oldAudit = $this->auditRows('commit-progressed')[0];
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(1, (int) $oldAudit['selected_count']);
        self::assertSame(1, (int) $oldAudit['delivered_count']);
        self::assertSame(2, $this->outboxCount());
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started'],
            $this->eventTypes($this->logicalOutbox()),
        );
        $beforeIngress = $this->outboxRows();
        self::assertSame([self::RECORD_ONE], $fixture['observer']->recordIds);

        $result = $fixture['runtime']->resume(
            'commit-progressed',
            'legacy-cli-actor',
            'legacy-cli-reason',
            now: new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
        self::assertTrue($result->complete);
        self::assertSame(2, $result->selected);
        self::assertSame(2, $result->delivered);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE], $fixture['observer']->recordIds);
        $audits = $this->auditRows('commit-progressed');
        self::assertCount(2, $audits);
        $oldRows = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] === $oldAudit['audit_id'],
        ));
        $newRows = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ));
        self::assertCount(1, $oldRows);
        self::assertCount(1, $newRows);
        self::assertSame('started', $oldRows[0]['state']);
        self::assertSame('complete', $newRows[0]['state']);
        self::assertSame(2, (int) $newRows[0]['selected_count']);
        self::assertSame(2, (int) $newRows[0]['delivered_count']);
        self::assertNotSame($oldRows[0]['audit_id'], $newRows[0]['audit_id']);
        self::assertSame(6, $fault->commitCalls());
        $final = $this->checkpointRow('commit-progressed');
        self::assertIsArray($final);
        self::assertSame('complete', $final['state']);
        self::assertSame('3|' . self::RECORD_THREE, $final['cursor_record_id']);
        self::assertSame(3, (int) $final['selected_count']);
        self::assertSame(3, (int) $final['delivered_count']);
        self::assertSame(6, $this->outboxCount());
        self::assertSame($oldAudit, $oldRows[0]);
        self::assertSame($beforeIngress, array_slice($this->outboxRows(), 0, count($beforeIngress)));
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $succeededActions = array_values(array_map(
            static fn(array $event): string => (string) $event['action']['id'],
            array_filter(
                $this->logicalOutbox(),
                static fn(array $event): bool => $event['event']['type'] === 'replay.observer.succeeded',
            ),
        ));
        self::assertSame(
            [(string) $deriver->derive(
                \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                'observer-replay:replay.observer.succeeded:' . $newRows[0]['audit_id'],
            )],
            $succeededActions,
        );
        self::assertNotContains(
            (string) $deriver->derive(
                \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                'observer-replay:replay.observer.succeeded:' . $oldAudit['audit_id'],
            ),
            $succeededActions,
        );
        self::assertSame(
            [
                'replay.observer.progressed',
                'replay.observer.progressed',
                'replay.observer.progressed',
                'replay.observer.started',
                'replay.observer.started',
                'replay.observer.succeeded',
            ],
            $this->eventTypes($this->logicalOutbox()),
        );
    }

    public function testCommitAfterThrowOnSucceededRootLeavesCompleteNativeAndC3Rows(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armCommitFaultAt(3, 'after');
        $fixture = $this->faultRuntime($fault);

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'commit-succeeded',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertSame('observer replay commit after', $failure->getMessage());
        self::assertSame(3, $fault->commitCalls());
        $checkpoint = $this->checkpointRow('commit-succeeded');
        self::assertIsArray($checkpoint);
        self::assertSame('complete', $checkpoint['state']);
        self::assertSame('1|' . self::RECORD_ONE, $checkpoint['cursor_record_id']);
        self::assertSame(1, (int) $checkpoint['selected_count']);
        self::assertSame(1, (int) $checkpoint['delivered_count']);
        self::assertSame('complete', $this->auditRows('commit-succeeded')[0]['state']);
        self::assertSame(1, (int) $this->auditRows('commit-succeeded')[0]['selected_count']);
        self::assertSame(1, (int) $this->auditRows('commit-succeeded')[0]['delivered_count']);
        self::assertSame(3, $this->outboxCount());
        self::assertSame(
            ['replay.observer.progressed', 'replay.observer.started', 'replay.observer.succeeded'],
            $this->eventTypes($this->logicalOutbox()),
        );
        self::assertSame([self::RECORD_ONE], $fixture['observer']->recordIds);
    }

    public function testCommitAfterThrowOnFailedRootLeavesOneCommittedFailureEvent(): void
    {
        $this->insertRecords([$this->record(self::RECORD_ONE, 1, $this->tenant())]);
        $fault = $this->faultConnection();
        $fault->armCommitFaultAt(2, 'after');
        $fixture = $this->faultRuntime($fault, new ObserverReplayFailingObserver());

        $failure = null;
        try {
            $fixture['runtime']->replay($this->request(
                'commit-failed',
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION)),
            ));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertSame(2, $fault->commitCalls());
        $checkpoint = $this->checkpointRow('commit-failed');
        self::assertIsArray($checkpoint);
        self::assertSame('failed', $checkpoint['state']);
        self::assertSame('failed', $this->auditRows('commit-failed')[0]['state']);
        self::assertSame(2, $this->outboxCount());
        self::assertSame(
            ['replay.observer.failed', 'replay.observer.started'],
            $this->eventTypes($this->logicalOutbox()),
        );
        self::assertSame([self::RECORD_ONE], $fixture['observer']->recordIds);
    }

    private function faultConnection(): ObserverReplayFaultConnection
    {
        $connection = new ObserverReplayFaultConnection($this->connection->getParams(), $this->connection->getDriver());
        $this->faultConnections[] = $connection;

        return $connection;
    }

    private function auditedStore(
        Connection $connection,
        ?ObserverReplayAuditClock $clock = null,
        ?Connection $writerConnection = null,
    ): PostgreSqlObserverReplayStore {
        $bundle = $this->auditBundle($writerConnection ?? $connection);
        $clock ??= new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $writer = new ObserverReplayAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $clock,
            new ActorContext(
                null,
                new ActorRef('authorized-operator', 'operator'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => '019fca20-0000-7000-8000-000000000099']),
            'observer-replay-audit-test',
        );

        return new PostgreSqlObserverReplayStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            hook: new ObserverReplayAuditHook($writer),
        );
    }

    /** @param Throwable $failure */
    private function assertSensitiveFrame(Throwable $failure, string $class, string $method, int $argumentCount): void
    {
        $frames = array_values(array_filter(
            $failure->getTrace(),
            static fn(array $frame): bool => (
                ($frame['class'] ?? null) === $class
                && ($frame['function'] ?? null) === $method
            ),
        ));
        self::assertNotEmpty($frames);
        $frame = $frames[0];
        self::assertArrayHasKey('args', $frame);
        self::assertIsArray($frame['args']);
        self::assertCount($argumentCount, $frame['args']);
        foreach ($frame['args'] as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
        }
    }

    /**
     * @return array{
     *     runtime:ObserverReplayRuntime,
     *     observer:ObserverReplayRecordingObserver|ObserverReplayFailingObserver,
     *     clock:ObserverReplayAuditClock
     * }
     */
    private function faultRuntime(
        ObserverReplayFaultConnection $connection,
        ?JournalObserver $observer = null,
        int $uuidOffset = 0,
    ): array {
        $bundle = $this->auditBundle($connection, $uuidOffset);
        $clock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.123456Z'));
        $writer = new ObserverReplayAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $clock,
            new ActorContext(
                null,
                new ActorRef('authorized-operator', 'operator'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => '019fca20-0000-7000-8000-000000000099']),
            'observer-replay-audit-test',
        );
        $store = new PostgreSqlObserverReplayStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            hook: new ObserverReplayAuditHook($writer),
        );
        $observer ??= new ObserverReplayRecordingObserver();

        return [
            'runtime' => new ObserverReplayRuntime(
                $store,
                new ObserverReplayTargetRegistry([
                    new \BlackOps\Internal\Journal\JournalObserverBinding('recording', $observer),
                ]),
                new \BlackOps\Internal\Projection\ObservedJournalRecordProjector(
                    new \BlackOps\Internal\Projection\SensitiveProjectionFilter(),
                ),
                100,
            ),
            'observer' => $observer,
            'clock' => $clock,
        ];
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"');
    }

    /** @return array<string, mixed>|false */
    private function checkpointRow(string $checkpoint): array|false
    {
        return $this->probe->fetchAssociative('SELECT state, cursor_record_id, selected_count, delivered_count, first_record_id, last_record_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => $checkpoint]);
    }

    /** @return list<array<string, mixed>> */
    private function auditRows(string $checkpoint): array
    {
        return $this->probe->fetchAllAssociative(
            'SELECT audit_id, checkpoint_id, selector_kind, selector_hash, target_hash, selector_operation_id, selector_record_id, selector_from, selector_to, actor, reason, target_names, state, failure_fingerprint, started_at, finished_at, selected_count, delivered_count, first_record_id, last_record_id FROM "'
            . self::SCHEMA
            . '"."observer_replay_audits" WHERE checkpoint_id=:id ORDER BY started_at, audit_id',
            ['id' => $checkpoint],
        );
    }

    /** @return list<array<string,mixed>> */
    private function outboxRows(): array
    {
        $rows = $this->probe->fetchAllAssociative(
            'SELECT record_id, partition_id, tenant_type, tenant_id, ingress_ordinal, idempotency_key_hash, content_hash, protected_body FROM "'
            . self::SCHEMA
            . '"."audit_outbox_records" ORDER BY ingress_ordinal',
        );
        foreach ($rows as &$row) {
            $row['ingress_ordinal'] = (int) $row['ingress_ordinal'];
            $row['protected_body'] = PostgreSqlBytea::string($row['protected_body'] ?? null);
        }
        unset($row);

        return $rows;
    }

    private function request(
        string $checkpoint,
        PostgreSqlObserverReplaySelector $selector,
        ?int $batchSize = null,
    ): ObserverReplayRequest {
        return new ObserverReplayRequest(
            $selector,
            ['recording'],
            $checkpoint,
            'legacy-cli-actor',
            'legacy-cli-reason',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
            $batchSize,
        );
    }

    /** @param list<JournalRecord> $records */
    private function insertRecords(array $records): void
    {
        $table = new PostgreSqlJournalSchema(self::SCHEMA)->journalTable();
        foreach ($records as $record) {
            $this->connection->executeStatement(
                'INSERT INTO ' . $table . ' (
                    record_id, operation_id, operation_type, sequence, event, attempt_id,
                    schema_version, operation_schema_version, occurred_at, tenant_type, tenant_id,
                    origin_actor_type, origin_actor_id, encoded_record
                ) VALUES (
                    :record_id, :operation_id, :operation_type, :sequence, :event, NULL,
                    :schema_version, :operation_schema_version, :occurred_at, :tenant_type, :tenant_id,
                    :origin_actor_type, :origin_actor_id, :encoded_record
                )',
                [
                    'record_id' => $record->recordId->toString(),
                    'operation_id' => $record->operation->id->toString(),
                    'operation_type' => $record->operation->type,
                    'sequence' => $record->sequence,
                    'event' => $record->event->value,
                    'schema_version' => $record->schemaVersion,
                    'operation_schema_version' => $record->operation->schemaVersion,
                    'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'tenant_type' => $record->operation->tenant?->type(),
                    'tenant_id' => $record->operation->tenant?->id(),
                    'origin_actor_type' => $record->operation->actorContext?->origin()?->type(),
                    'origin_actor_id' => $record->operation->actorContext?->origin()?->id(),
                    'encoded_record' => PostgreSqlTestStorageProtection::journalRecordEnvelope($record),
                ],
                ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
    }

    private function record(
        string $recordId,
        int $sequence,
        ?TenantRef $tenant,
        ?ActorContext $actorContext = null,
        ?string $correlationId = null,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString($recordId),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-09-29T12:00:0' . $sequence . 'Z'),
            $sequence,
            new JournalOperation(
                OperationId::fromString(self::OPERATION),
                'order.create',
                1,
                'inline',
                CorrelationId::fromString($correlationId ?? '019fca20-0000-7000-8000-000000000099'),
                actorContext: $actorContext,
                tenant: $tenant,
            ),
            null,
            new EmptyJournalData(),
        );
    }

    private function tenant(): TenantRef
    {
        return new TenantRef(self::TENANT_TYPE, self::TENANT_ID);
    }

    /** @param list<array<string,mixed>> $events @return list<string> */
    private function eventTypes(array $events): array
    {
        $types = array_map(static fn(array $event): string => $event['event']['type'], $events);
        sort($types);

        return $types;
    }

    /** @return list<array<string,mixed>> */
    private function logicalOutbox(): array
    {
        $rows = $this->probe->fetchAllAssociative('SELECT record_id, partition_id, tenant_type, tenant_id, ingress_ordinal, idempotency_key_hash,
                    content_hash, protected_body
             FROM "'
        . self::SCHEMA
        . '"."audit_outbox_records" ORDER BY ingress_ordinal');
        $logical = [];
        foreach ($rows as $row) {
            $ingress = \BlackOps\Internal\Audit\Protection\AuditProtectedIngress::fromArray([
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => $row['tenant_type'] === null
                    ? null
                    : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']],
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'protectedBody' => PostgreSqlBytea::string($row['protected_body'] ?? null),
            ]);
            $envelope = $this->auditProtection->unprotectOutbox($ingress);
            $value = $envelope->toArray();
            $logical[] = $value['logicalRecord'];
        }

        return $logical;
    }

    /** @return array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator,protection:AuditProtectedBodyCodec} */
    private function auditBundle(Connection $connection, int $uuidOffset = 0): array
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
            new ObserverReplayAuditKeySource(),
            new ObserverReplayAuditNonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $ingress,
        );
        $storeClock = new ObserverReplayAuditClock(new DateTimeImmutable('2026-09-29T12:00:00.654321Z'));
        $uuidValues = [];
        for ($index = 1; $index <= 64; ++$index) {
            $uuidValues[] = sprintf('019fca20-%04x-7000-8000-%012x', 0, 0x100 + $uuidOffset + $index);
        }
        $uuids = new ObserverReplayAuditUuidGenerator($uuidValues);
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $this->auditSchema,
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
}

final class ObserverReplayRecordingObserver implements JournalObserver
{
    /** @var list<string> */
    public array $recordIds = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->recordIds[] = $record->recordId->toString();
    }
}

final class ObserverReplayFailingObserver implements JournalObserver
{
    /** @var list<string> */
    public array $recordIds = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->recordIds[] = $record->recordId->toString();
        throw new JournalObservationFailed('observer replay test failure');
    }
}

final class ObserverReplayAuditClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly DateTimeImmutable $value,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return $this->value;
    }
}

final class ObserverReplayAuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'observer-replay-audit-' . $this->calls, true), 0, 24);
    }
}

final class ObserverReplayAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return $this->key('o2-' . $purpose->value, $tenant, $purpose);
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

final class ObserverReplayAuditUuidGenerator implements Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('observer replay audit UUID sequence exhausted.');
        }

        return $id;
    }
}

final class ObserverReplayFaultConnection extends Connection
{
    private int $appendAttempts = 0;
    private ?int $appendFaultAt = null;
    public int $commitCalls = 0;
    private ?int $commitFaultAt = null;
    private ?string $commitFault = null;
    private bool $finalizeFault = false;
    private bool $unlockTrace = false;

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function armAppendFaultAt(int $appendNumber): void
    {
        $this->appendFaultAt = $appendNumber;
    }

    public function armCommitFaultAt(int $commitNumber, string $mode): void
    {
        $this->commitFaultAt = $commitNumber;
        $this->commitFault = $mode;
    }

    public function armFinalizeFault(): void
    {
        $this->finalizeFault = true;
    }

    public function armUnlockTrace(): void
    {
        $this->unlockTrace = true;
    }

    public function appendAttempts(): int
    {
        return $this->appendAttempts;
    }

    public function commitCalls(): int
    {
        return $this->commitCalls;
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if ($this->unlockTrace && str_contains($query, 'pg_advisory_unlock')) {
            $this->unlockTrace = false;
            throw new ObserverReplayReleaseFault('observer replay release fault');
        }
        if (str_contains($query, 'audit_outbox_records') && str_contains($query, 'INSERT')) {
            $number = ++$this->appendAttempts;
            $result = parent::fetchOne($query, $params, $types);
            if ($this->appendFaultAt === $number) {
                $this->appendFaultAt = null;
                throw new \RuntimeException('observer replay append after');
            }

            return $result;
        }

        return parent::fetchOne($query, $params, $types);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (
            $this->finalizeFault
            && str_contains($sql, 'SET state=:state, updated_at=:at WHERE checkpoint_id=:id')
            && ($params['state'] ?? null) === 'complete'
        ) {
            $this->finalizeFault = false;
            throw new ObserverReplayFinalizeFault('observer replay finalize fault');
        }

        return parent::executeStatement($sql, $params, $types);
    }

    public function commit(): void
    {
        $number = ++$this->commitCalls;
        $fault = $this->commitFaultAt === $number ? $this->commitFault : null;
        if ($fault !== null) {
            $this->commitFaultAt = null;
            $this->commitFault = null;
        }
        parent::commit();
        if ($fault === 'after') {
            throw new \RuntimeException('observer replay commit after');
        }
    }
}

final class ObserverReplayFinalizeFault extends \RuntimeException {}

final class ObserverReplayReleaseFault extends \RuntimeException {}
