<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditHook;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
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
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\Outbox\OutboxRelayConfiguration;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxClaim;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;

final class PostgreSqlAuditedOutboxStoreTest extends TestCase
{
    private const string SCHEMA = 'blackops_generic_outbox_audit';
    private const string NOW = '2026-10-02T09:00:00.123456Z';
    private const string RECORD_A = '019fca20-0000-7000-8000-000000000101';
    private const string RECORD_B = '019fca20-0000-7000-8000-000000000102';
    private const string RECORD_GLOBAL = '019fca20-0000-7000-8000-000000000103';
    private const string RECORD_APPEND_FAULT = '019fca20-0000-7000-8000-000000000104';
    private const string RECORD_COMMIT_BEFORE = '019fca20-0000-7000-8000-000000000105';
    private const string RECORD_COMMIT_AFTER = '019fca20-0000-7000-8000-000000000106';
    private const string RECORD_FAULT_RETRY = '019fca20-0000-7000-8000-000000000107';
    private const string RECORD_FAULT_DEAD = '019fca20-0000-7000-8000-000000000108';
    private const string RECORD_FAULT_MANUAL = '019fca20-0000-7000-8000-000000000109';
    private const string RECORD_RUNTIME_STALE = '019fca20-0000-7000-8000-00000000010a';
    private const string RECORD_RUNTIME_SENT = '019fca20-0000-7000-8000-00000000010b';
    private const string RECORD_RUNTIME_CLEANUP = '019fca20-0000-7000-8000-00000000010c';
    private const string RECORD_RUNTIME_RETRY = '019fca20-0000-7000-8000-00000000010d';
    private const string RECORD_RUNTIME_DEAD_STALE = '019fca20-0000-7000-8000-00000000010e';
    private const string RECORD_RUNTIME_DEAD_SENT = '019fca20-0000-7000-8000-00000000010f';
    private const string RECORD_RUNTIME_CLEANUP_SENT = '019fca20-0000-7000-8000-000000000110';
    private const string OPERATION_BASE = '019fca20-0000-7000-8000-000000000201';
    private const string TENANT_A = 'tenant-a';
    private const string TENANT_B = 'tenant-b';
    private const string BUILD_ID = 'generic-outbox-audit-test';
    private const string FINGERPRINT = 'v1:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string C3_CANARY = 'generic-outbox-c3-after-ingress';
    private const string FORGED_OPERATION_ID = '019fca20-0000-7000-8000-0000000002f0';
    private const string FORGED_CORRELATION_ID = '019fca20-0000-7000-8000-0000000002f1';
    private const string FORGED_ACTOR_ID = 'forged-actor-marker';
    private const string FORGED_PAYLOAD_MARKER = 'forged-payload-marker';

    private Connection $admin;
    private Connection $connection;
    private Connection $probe;
    private PostgreSqlAuditSchema $auditSchema;
    private PostgreSqlAuditOutboxSchema $auditOutboxSchema;
    private PostgreSqlOutboxSchema $nativeSchema;
    private AuditProtectedBodyCodec $auditProtection;
    private OutboxAuditClock $clock;

    /** @var list<Connection> */
    private array $faultConnections = [];

    protected function setUp(): void
    {
        $this->admin = $this->connection();
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        $this->admin->executeStatement('CREATE SCHEMA "' . self::SCHEMA . '"');
        $this->auditSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        $this->auditOutboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema(self::SCHEMA));
        $this->nativeSchema = new PostgreSqlOutboxSchema(self::SCHEMA);
        foreach ([
            ...$this->auditSchema->statements(),
            ...$this->auditOutboxSchema->statements(),
            ...$this->nativeSchema->statements(),
        ] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $bundle = $this->auditBundle($this->connection);
        $this->auditProtection = $bundle['protection'];
        $this->clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:00:00.654321Z'));
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

    public function testEnabledAutomaticRootsCommitNativeRowsAndCurrentAuditIngress(): void
    {
        $base = $this->legacyStore($this->connection);
        $base->insert($this->record(self::RECORD_A, self::OPERATION_BASE, $this->tenant(self::TENANT_A)));
        $base->insert($this->record(self::RECORD_B, $this->operation(2), $this->tenant(self::TENANT_B)));
        $base->insert($this->record(self::RECORD_GLOBAL, $this->operation(3), null));
        $store = $this->relayStore($this->connection, $this->clock);
        $now = new DateTimeImmutable(self::NOW);

        $claimA = $store->claimBatch('relay-a', 1, $now, 60)[0];
        $forged = $this->claimWithProvenance($claimA, $this->tenant(self::TENANT_B));
        $store->scheduleRetry($forged, $now->modify('+1 minute'), self::FINGERPRINT);
        $claimB = $store->claimBatch('relay-a', 1, $now, 60)[0];
        $store->moveToDeadLetter($claimB, self::FINGERPRINT);
        $claimGlobal = $store->claimBatch('relay-a', 1, $now, 60)[0];
        $store->scheduleRetry($claimGlobal, $now->modify('+2 minutes'), self::FINGERPRINT);

        self::assertSame(3, $this->clock->calls);
        self::assertSame('retry_scheduled', $this->nativeState(self::RECORD_A));
        self::assertSame('dead_lettered', $this->nativeState(self::RECORD_B));
        self::assertSame('retry_scheduled', $this->nativeState(self::RECORD_GLOBAL));
        $nativeIdentity = $this->probe->fetchAssociative('SELECT operation_id::text AS operation_id, encoded_payload, encoded_context FROM "'
        . self::SCHEMA
        . '"."outbox_records" WHERE record_id=:id', ['id' => self::RECORD_A]);
        self::assertIsArray($nativeIdentity);
        self::assertSame(self::OPERATION_BASE, $nativeIdentity['operation_id']);
        self::assertStringNotContainsString(self::FORGED_OPERATION_ID, (string) $nativeIdentity['encoded_context']);
        self::assertStringNotContainsString(self::FORGED_CORRELATION_ID, (string) $nativeIdentity['encoded_context']);
        self::assertStringNotContainsString(self::FORGED_PAYLOAD_MARKER, (string) $nativeIdentity['encoded_payload']);
        $events = $this->logicalOutbox();
        self::assertCount(3, $events);
        $this->assertOutboxEvent(
            $events[0],
            'outbox.delivery.retry_scheduled',
            AuditSourceRuntime::Relay,
            $this->tenant(self::TENANT_A),
            self::RECORD_A,
            '3',
        );
        $this->assertOutboxEvent(
            $events[1],
            'outbox.delivery.dead_lettered',
            AuditSourceRuntime::Relay,
            $this->tenant(self::TENANT_B),
            self::RECORD_B,
            '3',
        );
        $this->assertOutboxEvent(
            $events[2],
            'outbox.delivery.retry_scheduled',
            AuditSourceRuntime::Relay,
            null,
            self::RECORD_GLOBAL,
            '3',
        );
        self::assertStringNotContainsString(self::TENANT_B, json_encode($events[0], JSON_THROW_ON_ERROR));
        $forgedEvent = json_encode($events[0], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::FORGED_OPERATION_ID, $forgedEvent);
        self::assertStringNotContainsString(self::FORGED_CORRELATION_ID, $forgedEvent);
        self::assertStringNotContainsString(self::FORGED_ACTOR_ID, $forgedEvent);
        self::assertStringNotContainsString(self::FORGED_PAYLOAD_MARKER, $forgedEvent);
        foreach ($events as $index => $event) {
            $this->assertDurableIdempotencyKey($event, $this->durableAuditRow($index));
        }
    }

    public function testOperatorRetrySharesNativeUuidLegacyAuditAndDurableIngress(): void
    {
        $this->legacyStore($this->connection)->insert($this->record(
            self::RECORD_B,
            self::OPERATION_BASE,
            $this->tenant(self::TENANT_B),
        ));
        $now = new DateTimeImmutable(self::NOW);
        $relay = $this->relayStore($this->connection, $this->clock);
        $claim = $relay->claimBatch('relay-a', 1, $now, 60)[0];
        $relay->moveToDeadLetter($claim, self::FINGERPRINT);
        $cliClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:01:00.654321Z'));
        $cli = $this->cliStore(
            $this->connection,
            $cliClock,
            $this->auditBundle($this->connection, 500, new DateTimeImmutable('2026-10-02T09:01:00.654321Z')),
        );
        $cli->retryDeadLetter($claim->recordId, 'operator-visible', 'manual retry', $now);

        self::assertSame('retry_scheduled', $this->nativeState(self::RECORD_B));
        self::assertSame(
            '4',
            (string) $this->probe->fetchOne('SELECT state_version FROM "'
            . self::SCHEMA
            . '"."outbox_records" WHERE record_id=:id', ['id' => self::RECORD_B]),
        );
        $retryAudit = $this->probe->fetchAssociative('SELECT audit_id::text AS audit_id, actor, reason, previous_attempt_count FROM "'
        . self::SCHEMA
        . '"."outbox_dead_letter_retry_audits" WHERE record_id=:id', ['id' => self::RECORD_B]);
        self::assertIsArray($retryAudit);
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
            (string) $retryAudit['audit_id'],
        );
        self::assertSame('operator-visible', $retryAudit['actor']);
        self::assertSame('manual retry', $retryAudit['reason']);
        $events = $this->logicalOutbox();
        self::assertCount(2, $events);
        $this->assertOutboxEvent(
            $events[0],
            'outbox.delivery.dead_lettered',
            AuditSourceRuntime::Relay,
            $this->tenant(self::TENANT_B),
            self::RECORD_B,
            '3',
        );
        $this->assertOutboxEvent(
            $events[1],
            'outbox.delivery.dead_letter_retry_requested',
            AuditSourceRuntime::Cli,
            $this->tenant(self::TENANT_B),
            self::RECORD_B,
            null,
            (string) $retryAudit['audit_id'],
        );
        $encoded = json_encode($events[1], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('operator-visible', $encoded);
        self::assertStringNotContainsString('manual retry', $encoded);
    }

    public function testNullHookPreservesLegacyMutationAndDoesNotCreateAuditIngress(): void
    {
        $base = $this->legacyStore($this->connection);
        $base->insert($this->record(self::RECORD_A, self::OPERATION_BASE, null));
        $now = new DateTimeImmutable(self::NOW);
        $claim = $base->claimBatch('relay-a', 1, $now, 60)[0];
        $base->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
        self::assertSame('retry_scheduled', $this->nativeState(self::RECORD_A));
        self::assertSame(
            0,
            (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"'),
        );

        $claim = $base->claimBatch('relay-a', 1, $now->modify('+2 minutes'), 60)[0];
        $base->moveToDeadLetter($claim, self::FINGERPRINT);
        $base->retryDeadLetter($claim->recordId, 'operator', 'legacy retry', $now);
        self::assertSame(
            0,
            (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"'),
        );
    }

    public function testOwnedFailuresRollbackAcceptedIngressAndExposeCommitAfterEffect(): void
    {
        $base = $this->legacyStore($this->connection);
        foreach ([
            [self::RECORD_APPEND_FAULT, $this->operation(6)],
            [self::RECORD_COMMIT_BEFORE, $this->operation(4)],
            [self::RECORD_COMMIT_AFTER, $this->operation(5)],
        ] as [$recordId, $operationId]) {
            $base->insert($this->record($recordId, $operationId, null));
        }
        $now = new DateTimeImmutable(self::NOW);

        $appendFault = $this->faultConnection();
        $appendBundle = $this->auditBundle($appendFault, 100, new DateTimeImmutable('2026-10-02T09:02:00.654321Z'));
        $appendClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:02:00.654321Z'));
        $appendStore = $this->relayStore($appendFault, $appendClock, $appendBundle);
        $appendClaim = $appendStore->claimBatch('relay-append', 1, $now, 60)[0];
        $appendFault->resetTrace();
        $appendFault->armAppendFaultAt(1);
        $this->assertOwnedFailure($appendStore, $appendClaim, $appendFault, $appendClock, 0, 1, 1);
        self::assertSame('leased', $this->nativeState(self::RECORD_APPEND_FAULT));
        self::assertSame(0, $this->auditCount());
        $reconnected = $this->legacyStore($this->connection)->claimBatch(
            'relay-reconnect',
            1,
            $now->modify('+61 seconds'),
            60,
        );
        self::assertCount(1, $reconnected);

        $beforeFault = $this->faultConnection();
        $beforeBundle = $this->auditBundle($beforeFault, 200, new DateTimeImmutable('2026-10-02T09:03:00.654321Z'));
        $beforeClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:03:00.654321Z'));
        $beforeStore = $this->relayStore($beforeFault, $beforeClock, $beforeBundle);
        $beforeClaim = $beforeStore->claimBatch('relay-before', 1, $now, 60)[0];
        $beforeFault->resetTrace();
        $beforeFault->armCommitFault('before');
        $this->assertOwnedFailure($beforeStore, $beforeClaim, $beforeFault, $beforeClock, 1, 1, 1);
        self::assertSame('leased', $this->nativeState(self::RECORD_COMMIT_BEFORE));
        self::assertSame(0, $this->auditCount());

        $afterFault = $this->faultConnection();
        $afterBundle = $this->auditBundle($afterFault, 300, new DateTimeImmutable('2026-10-02T09:04:00.654321Z'));
        $afterClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:04:00.654321Z'));
        $afterStore = $this->relayStore($afterFault, $afterClock, $afterBundle);
        $afterClaim = $afterStore->claimBatch('relay-after', 1, $now, 60)[0];
        $afterFault->resetTrace();
        $afterFault->armCommitFault('after');
        $this->assertOwnedFailure($afterStore, $afterClaim, $afterFault, $afterClock, 1, 0, 1);
        self::assertSame('retry_scheduled', $this->nativeState(self::RECORD_COMMIT_AFTER));
        self::assertSame(1, $this->auditCount());
        $events = $this->logicalOutbox();
        self::assertCount(1, $events);
        $this->assertOutboxEvent(
            $events[0],
            'outbox.delivery.retry_scheduled',
            AuditSourceRuntime::Relay,
            null,
            self::RECORD_COMMIT_AFTER,
            '3',
        );
    }

    public function testAllEnabledRootsCommitNativeRowsAndAuditForEveryTenant(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:30:00.654321Z'));
        $cases = [];
        $index = 0;
        foreach (['schedule', 'dead', 'manual'] as $root) {
            $records = [];
            foreach ([$this->tenant(self::TENANT_A), $this->tenant(self::TENANT_B), null] as $tenant) {
                ++$index;
                $recordId = $this->fixedId(0x250 + $index);
                $this->insertRecord($recordId, $this->fixedId(0x350 + $index), $tenant);
                $records[] = [$recordId, $tenant, $index];
            }
            $legacy = $this->legacyStore($this->connection);
            if ($root === 'manual') {
                $claims = $legacy->claimBatch('positive-manual', 3, $now, 60);
                $claimsById = [];
                foreach ($claims as $claim) {
                    $claimsById[$claim->recordId->toString()] = $claim;
                    $legacy->moveToDeadLetter($claim, self::FINGERPRINT);
                }
                foreach ($records as [$recordId, $tenant, $recordIndex]) {
                    $store = $this->cliStore(
                        $this->connection,
                        $clock,
                        $this->auditBundle(
                            $this->connection,
                            1000 + $recordIndex,
                            new DateTimeImmutable('2026-10-02T09:30:00.654321Z'),
                        ),
                    );
                    $store->retryDeadLetter($claimsById[$recordId]->recordId, 'operator-visible', 'manual retry', $now);
                    $retryAuditId = (string) $this->probe->fetchOne('SELECT audit_id::text FROM "'
                    . self::SCHEMA
                    . '"."outbox_dead_letter_retry_audits" WHERE record_id=:id', ['id' => $recordId]);
                    $cases[] = [$root, $tenant, $recordId, '4', $retryAuditId];
                }
                continue;
            }
            foreach ($records as [$recordId, $tenant, $recordIndex]) {
                $store = $this->relayStore(
                    $this->connection,
                    $clock,
                    $this->auditBundle(
                        $this->connection,
                        1000 + $recordIndex,
                        new DateTimeImmutable('2026-10-02T09:30:00.654321Z'),
                    ),
                );
                $claim = $legacy->claimBatch('positive-' . $root, 1, $now, 60)[0];
                if ($root === 'schedule') {
                    $store->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
                } else {
                    $store->moveToDeadLetter($claim, self::FINGERPRINT);
                }
                $cases[] = [$root, $tenant, $recordId, '3', null];
            }
        }

        self::assertSame(9, $clock->calls);
        $events = $this->logicalOutbox();
        self::assertCount(9, $events);
        $eventIndex = 0;
        foreach ($cases as [$root, $tenant, $recordId, $stateVersion, $retryAuditId]) {
            $eventType = match ($root) {
                'schedule' => 'outbox.delivery.retry_scheduled',
                'dead' => 'outbox.delivery.dead_lettered',
                default => 'outbox.delivery.dead_letter_retry_requested',
            };
            $runtime = $root === 'manual' ? AuditSourceRuntime::Cli : AuditSourceRuntime::Relay;
            $this->assertOutboxEvent(
                $events[$eventIndex++],
                $eventType,
                $runtime,
                $tenant,
                $recordId,
                $root === 'manual' ? null : $stateVersion,
                $retryAuditId,
            );
            self::assertSame(
                $root === 'schedule' ? 'retry_scheduled' : ($root === 'dead' ? 'dead_lettered' : 'retry_scheduled'),
                $this->nativeState($recordId),
            );
        }
    }

    public function testEveryEnabledRootReachesItsInjectedFaultAndReconnectsOnTheAffectedConnection(): void
    {
        $faults = [
            'begin-before',
            'begin-after',
            'post-begin-autocommit',
            'post-begin-inactive',
            'post-begin-nested',
            'native-before',
            'native-malformed',
            'c3-before',
            'c3-after',
            'commit-before',
            'commit-after',
            'probe-after-begin',
            'cleanup-probe',
            'rollback-before',
            'rollback-after',
            'close',
        ];
        $roots = ['schedule', 'dead', 'manual'];
        $case = 0;
        $now = new DateTimeImmutable(self::NOW);

        foreach ($roots as $root) {
            foreach ($faults as $fault) {
                ++$case;
                $recordId = $this->fixedId(0x400 + $case);
                $this->insertRecord(
                    $recordId,
                    $this->operation(0x300 + $case),
                    ($case % 3) === 0 ? null : $this->tenant(($case % 2) === 0 ? self::TENANT_B : self::TENANT_A),
                );
                $connection = $this->faultConnection();
                $clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T10:00:00.654321Z'));
                $store = $this->relayStore(
                    $connection,
                    $clock,
                    $this->auditBundle($connection, 600 + $case, new DateTimeImmutable('2026-10-02T10:00:00.654321Z')),
                );
                $claim = null;
                if ($root === 'manual') {
                    $legacy = $this->legacyStore($this->connection);
                    $claim = $legacy->claimBatch('manual-setup', 1, $now, 60)[0];
                    $legacy->moveToDeadLetter($claim, self::FINGERPRINT);
                    $store = $this->cliStore(
                        $connection,
                        $clock,
                        $this->auditBundle(
                            $connection,
                            700 + $case,
                            new DateTimeImmutable('2026-10-02T10:00:00.654321Z'),
                        ),
                    );
                } else {
                    $claim = $store->claimBatch('fault-matrix', 1, $now, 60)[0];
                }
                /** @var PostgreSqlOutboxClaim $claim */
                $sentinelId = $this->seedCommittedSentinel($connection, $case);
                self::assertSame('retry_scheduled', $this->nativeState($sentinelId));
                $sentinelIngress = $this->durableIngressForNativeRecord($sentinelId);
                self::assertNotEmpty($sentinelIngress, $root . '/' . $fault . ' sentinel ingress');
                $sentinelAuditCount = $this->auditCount();
                $connection->resetTrace();
                $this->armFault($connection, $fault);
                $beforeAudit = $sentinelAuditCount;

                $failed = false;
                $failure = null;
                try {
                    $this->invokeRoot($root, $store, $claim, $recordId, $now);
                } catch (DeferredTransportException $exception) {
                    $failed = true;
                    $failure = $exception;
                }
                self::assertTrue($failed, $root . '/' . $fault . ' did not expose a safe failure');
                self::assertInstanceOf(DeferredTransportException::class, $failure);
                self::assertSame(
                    match ($root) {
                        'schedule' => 'Failed to schedule PostgreSQL outbox retry.',
                        'dead' => 'Failed to dead-letter PostgreSQL outbox record.',
                        default => 'Failed to retry dead-lettered outbox record.',
                    },
                    $failure->getMessage(),
                );
                self::assertNull($failure->getPrevious());
                self::assertStringNotContainsString(self::C3_CANARY, $failure->getMessage());
                self::assertStringNotContainsString(self::C3_CANARY, $failure->getTraceAsString());
                self::assertSame(1, $connection->closeCalls(), $root . '/' . $fault . ' close count');
                if (str_starts_with($fault, 'post-begin-')) {
                    self::assertSame(0, $connection->nativeAttempts(), $root . '/' . $fault . ' native effect');
                    self::assertSame(0, $connection->appendAttempts(), $root . '/' . $fault . ' C3 effect');
                    self::assertSame(0, $clock->calls, $root . '/' . $fault . ' clock effect');
                    self::assertSame([], $connection->sqlTrace(), $root . '/' . $fault . ' SQL effect');
                }
                $expectedRollbacks = match ($fault) {
                    'begin-before', 'commit-after', 'cleanup-probe' => 0,
                    default => 1,
                };
                self::assertSame(
                    $expectedRollbacks,
                    $connection->rollbackCalls(),
                    $root . '/' . $fault . ' rollback trace',
                );
                $this->assertFaultReached($connection, $fault, $root);
                if ($root === 'manual') {
                    if (in_array(
                        $fault,
                        [
                            'begin-before',
                            'begin-after',
                            'post-begin-autocommit',
                            'post-begin-inactive',
                            'post-begin-nested',
                            'probe-after-begin',
                        ],
                        true,
                    )) {
                        self::assertSame([], $connection->sqlTrace(), $root . '/' . $fault . ' SQL rejection trace');
                    } else {
                        $this->assertSqlTraceContains($connection, 'SELECT ', $root . '/' . $fault . ' native SELECT');
                        $this->assertSqlTraceContains(
                            $connection,
                            'outbox_records',
                            $root . '/' . $fault . ' native SQL',
                        );
                    }
                    if (in_array(
                        $fault,
                        [
                            'c3-before',
                            'c3-after',
                            'commit-before',
                            'commit-after',
                            'cleanup-probe',
                            'rollback-before',
                            'rollback-after',
                            'close',
                        ],
                        true,
                    )) {
                        $this->assertSqlTraceContains($connection, 'UPDATE ', $root . '/' . $fault . ' native UPDATE');
                        $this->assertSqlTraceContains(
                            $connection,
                            'outbox_dead_letter_retry_audits',
                            $root . '/' . $fault . ' legacy retry INSERT',
                        );
                    }
                }

                $committed = $fault === 'commit-after';
                $expectedState = match ($root) {
                    'schedule' => $committed ? 'retry_scheduled' : 'leased',
                    'dead' => $committed ? 'dead_lettered' : 'leased',
                    default => $committed ? 'retry_scheduled' : 'dead_lettered',
                };
                self::assertSame($expectedState, $this->nativeState($recordId));
                self::assertSame($committed ? $beforeAudit + 1 : $beforeAudit, $this->auditCount());
                self::assertSame('retry_scheduled', $this->nativeState($sentinelId));
                self::assertSame($sentinelIngress, $this->durableIngressForNativeRecord($sentinelId));
                self::assertSame($sentinelAuditCount, $this->auditCount() - ($committed ? 1 : 0));
                self::assertTrue($connection->isClosed(), $root . '/' . $fault . ' closed after failure');
                if ($committed) {
                    $this->assertCommitAfterStaleAndLaterCycle(
                        $connection,
                        $root,
                        $store,
                        $claim,
                        $recordId,
                        $now,
                        $case,
                    );
                }
                if ($root === 'manual') {
                    self::assertSame(
                        $committed ? 1 : 0,
                        (int) $this->probe->fetchOne('SELECT count(*) FROM "'
                        . self::SCHEMA
                        . '"."outbox_dead_letter_retry_audits" WHERE record_id=:id', ['id' => $recordId]),
                    );
                    if ($committed) {
                        $this->probe->executeStatement('UPDATE "'
                        . self::SCHEMA
                        . '"."outbox_records" SET next_attempt_at=:future WHERE record_id=:id', [
                            'future' => $now->modify('+1 hour')->format('Y-m-d H:i:s.uP'),
                            'id' => $recordId,
                        ]);
                    }
                }

                $reconnectedId = $this->fixedId(0x900 + $case);
                $reconnected = $this->legacyStore($connection);
                $reconnected->insert($this->record($reconnectedId, $this->operation(0x700 + $case), null));
                $reconnectedClaims = $reconnected->claimBatch(
                    'affected-reconnect',
                    100,
                    $now->modify('+61 seconds'),
                    60,
                );
                $matching = array_values(array_filter(
                    $reconnectedClaims,
                    static fn(PostgreSqlOutboxClaim $candidate): bool => (
                        $candidate->recordId->toString() === $reconnectedId
                    ),
                ));
                self::assertCount(1, $matching, $root . '/' . $fault . ' did not reconnect');
                $reconnected->markSent($matching[0]);
                self::assertSame('sent', $this->nativeState($reconnectedId));
                self::assertSame('retry_scheduled', $this->nativeState($sentinelId));
                self::assertSame($sentinelIngress, $this->durableIngressForNativeRecord($sentinelId));
                self::assertSame($sentinelAuditCount, $this->auditCount() - ($committed ? 2 : 0));
            }
        }
    }

    public function testNativeReturnedRowsPreserveExactBigintAndOverflowRollsBackWithoutIngress(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $maxRecord = self::RECORD_FAULT_RETRY;
        $this->insertRecord($maxRecord, self::OPERATION_BASE, null);
        $maxStore = $this->relayStore($this->connection, $this->clock);
        $maxClaim = $maxStore->claimBatch('bigint-max', 1, $now, 60)[0];
        $this->probe->executeStatement('UPDATE "'
        . self::SCHEMA
        . '"."outbox_records" SET state_version=:version WHERE record_id=:id', [
            'version' => '9223372036854775806',
            'id' => $maxRecord,
        ]);
        $maxStore->scheduleRetry($maxClaim, $now->modify('+1 minute'), self::FINGERPRINT);
        self::assertSame('retry_scheduled', $this->nativeState($maxRecord));
        $events = $this->logicalOutbox();
        self::assertCount(1, $events);
        $this->assertOutboxEvent(
            $events[0],
            'outbox.delivery.retry_scheduled',
            AuditSourceRuntime::Relay,
            null,
            $maxRecord,
            '9223372036854775807',
        );

        $overflowRecord = self::RECORD_FAULT_DEAD;
        $this->insertRecord($overflowRecord, $this->operation(17), null);
        $overflowConnection = $this->faultConnection();
        $overflowStore = $this->relayStore(
            $overflowConnection,
            new OutboxAuditClock(new DateTimeImmutable('2026-10-02T10:30:00.654321Z')),
            $this->auditBundle($overflowConnection, 810),
        );
        $overflowClaim = $overflowStore->claimBatch('bigint-overflow', 1, $now, 60)[0];
        $this->probe->executeStatement('UPDATE "'
        . self::SCHEMA
        . '"."outbox_records" SET state_version=:version WHERE record_id=:id', [
            'version' => '9223372036854775807',
            'id' => $overflowRecord,
        ]);
        $failed = false;
        try {
            $overflowStore->scheduleRetry($overflowClaim, $now->modify('+1 minute'), self::FINGERPRINT);
        } catch (DeferredTransportException) {
            $failed = true;
        }
        self::assertTrue($failed, 'native BIGINT overflow was accepted');
        self::assertSame('leased', $this->nativeState($overflowRecord));
        self::assertCount(1, $this->logicalOutbox());
        self::assertSame(1, $overflowConnection->closeCalls());
    }

    public function testWriterBindsFourStringBigintVersionsThroughCurrentTransactionC3(): void
    {
        $recordedAt = new DateTimeImmutable('2026-10-02T11:30:00.654321Z');
        $connection = $this->faultConnection();
        $clock = new OutboxAuditClock($recordedAt);
        $bundle = $this->auditBundle($connection, 2300, $recordedAt);
        $hook = new GenericOutboxAuditHook($this->writer($bundle, $clock, AuditSourceRuntime::Relay));
        $versions = [
            '1',
            '9007199254740991',
            '9007199254740992',
            '9223372036854775807',
        ];
        $recordIds = [
            $this->fixedId(0xf30),
            $this->fixedId(0xf31),
            $this->fixedId(0xf32),
            $this->fixedId(0xf33),
        ];

        foreach ($versions as $index => $version) {
            $connection->beginTransaction();
            $hook->retryScheduled($connection, [
                'record_id' => $recordIds[$index],
                'tenant_type' => null,
                'tenant_id' => null,
                'state_version' => $version,
            ]);
            $connection->commit();
        }

        self::assertSame(count($versions), $clock->calls);
        self::assertSame(count($versions), $connection->appendAttempts());
        self::assertSame(count($versions), $connection->appendWrites());
        $events = $this->logicalOutbox();
        self::assertCount(count($versions), $events);
        $expectedActionIds = [];
        $actualActionIds = [];
        $actualAuditIds = [];
        $actualKeyHashes = [];
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));

        foreach ($versions as $index => $version) {
            $event = $events[$index];
            $recordId = $recordIds[$index];
            $this->assertOutboxEvent(
                $event,
                'outbox.delivery.retry_scheduled',
                AuditSourceRuntime::Relay,
                null,
                $recordId,
                $version,
            );
            $expectedAction = (string) $deriver->derive(
                AuditOpaqueIdPurpose::Action,
                'generic-outbox:outbox.delivery.retry_scheduled:' . $recordId . ':' . $version,
            );
            self::assertSame($expectedAction, $event['action']['id']);
            $row = $this->durableAuditRow($index);
            $this->assertDurableIdempotencyKey($event, $row);
            $ingress = $this->durableIngressForNativeRecord($recordId);
            self::assertCount(1, $ingress, 'one protected ingress for ' . $version);
            self::assertSame($event['action']['id'], $ingress[0]['actionId']);
            self::assertSame((string) $row['record_id'], $ingress[0]['auditRecordId']);
            self::assertSame((string) $row['idempotency_key_hash'], $ingress[0]['idempotencyKeyHash']);
            $expectedActionIds[] = $expectedAction;
            $actualActionIds[] = $ingress[0]['actionId'];
            $actualAuditIds[] = $ingress[0]['auditRecordId'];
            $actualKeyHashes[] = $ingress[0]['idempotencyKeyHash'];
        }

        self::assertCount(count($versions), array_unique($expectedActionIds));
        self::assertCount(count($versions), array_unique($actualActionIds));
        self::assertCount(count($versions), array_unique($actualAuditIds));
        self::assertCount(count($versions), array_unique($actualKeyHashes));
    }

    public function testNativeOwnedFramesRedactSensitiveMarkersWithExceptionArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertIsString($previous);
        ini_set('zend.exception_ignore_args', '0');
        self::assertSame('0', ini_get('zend.exception_ignore_args'));
        try {
            $recordId = $this->fixedId(0xe01);
            $this->insertRecord($recordId, $this->operation(0xe01), null);
            $now = new DateTimeImmutable(self::NOW);
            $connection = $this->faultConnection();
            $clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T10:45:00.654321Z'));
            $store = $this->relayStore(
                $connection,
                $clock,
                $this->auditBundle($connection, 1801, new DateTimeImmutable('2026-10-02T10:45:00.654321Z')),
            );
            $claim = $store->claimBatch('native-privacy', 1, $now, 60)[0];
            $claim = $this->claimWithProvenance($claim, $this->tenant(self::TENANT_B));
            $connection->resetTrace();
            $connection->armAppendFaultAt(1);
            $marker = 'v1:' . str_repeat('b', 64);
            $failure = null;
            try {
                $store->scheduleRetry($claim, $now->modify('+1 minute'), $marker);
            } catch (DeferredTransportException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(DeferredTransportException::class, $failure);
            self::assertSame('Failed to schedule PostgreSQL outbox retry.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString($marker, $failure->getMessage());
            self::assertStringNotContainsString($marker, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::FORGED_OPERATION_ID, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::FORGED_CORRELATION_ID, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::FORGED_PAYLOAD_MARKER, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::FORGED_ACTOR_ID, $failure->getTraceAsString());
            $this->assertSensitiveStoreFrame($failure, 'scheduleRetryEnabled', $marker, 3);
            $this->assertSensitiveStoreFrame($failure, 'scheduleRetry', $marker, 3);
            self::assertSame(1, $connection->appendAttempts());
            self::assertSame(1, $connection->appendWrites());

            $manualRecordId = $this->fixedId(0xe02);
            $this->insertRecord($manualRecordId, $this->operation(0xe02), null);
            $manualClaim = $this->legacyStore($this->connection)->claimBatch('native-privacy-cli', 1, $now, 60)[0];
            $this->legacyStore($this->connection)->moveToDeadLetter($manualClaim, self::FINGERPRINT);
            $manualConnection = $this->faultConnection();
            $manualClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T11:00:00.654321Z'));
            $manualStore = $this->cliStore(
                $manualConnection,
                $manualClock,
                $this->auditBundle($manualConnection, 1820, new DateTimeImmutable('2026-10-02T11:00:00.654321Z')),
            );
            $manualConnection->armAppendFaultAt(1);
            $actorMarker = 'native-operator-sensitive-marker';
            $reasonMarker = 'native-reason-sensitive-marker';
            $manualFailure = null;
            try {
                $manualStore->retryDeadLetter(
                    OutboxRecordId::fromString($manualRecordId),
                    $actorMarker,
                    $reasonMarker,
                    $now,
                );
            } catch (DeferredTransportException $exception) {
                $manualFailure = $exception;
            }
            self::assertInstanceOf(DeferredTransportException::class, $manualFailure);
            self::assertSame('Failed to retry dead-lettered outbox record.', $manualFailure->getMessage());
            self::assertNull($manualFailure->getPrevious());
            self::assertStringNotContainsString($actorMarker, $manualFailure->getTraceAsString());
            self::assertStringNotContainsString($reasonMarker, $manualFailure->getTraceAsString());
            $this->assertSensitiveStoreFrame($manualFailure, 'retryDeadLetterEnabled', $actorMarker, 4);
            $this->assertSensitiveStoreFrame($manualFailure, 'retryDeadLetter', $actorMarker, 4);
            self::assertSame(1, $manualConnection->appendAttempts());
            self::assertSame(1, $manualConnection->appendWrites());
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
            self::assertSame($previous, ini_get('zend.exception_ignore_args'));
        }
    }

    public function testNativeNonMutationAndStaleTransitionsRemainSilentWithAnEnabledHook(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $legacy = $this->legacyStore($this->connection);
        $legacy->migrate();
        $this->insertRecord(self::RECORD_A, self::OPERATION_BASE, null);
        $first = $legacy->claimBatch('silent-relay', 1, $now, 60)[0];
        $legacy->heartbeat($first, $now, 60);
        $legacy->markSent($first);
        self::assertSame('sent', $this->nativeState(self::RECORD_A));

        $this->insertRecord(self::RECORD_B, $this->operation(19), null);
        $leased = $legacy->claimBatch('silent-relay', 1, $now, 60)[0];
        $reclaimed = $legacy->claimBatch('silent-relay-next', 1, $now->modify('+61 seconds'), 60)[0];
        self::assertSame($leased->recordId->toString(), $reclaimed->recordId->toString());
        $legacy->markSent($reclaimed);
        self::assertSame('sent', $this->nativeState(self::RECORD_B));

        $wrongTargetConnection = $this->faultConnection();
        $wrongTargetStore = $this->cliStore(
            $wrongTargetConnection,
            new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:45:00.654321Z')),
        );
        $wrongTargetFailure = null;
        try {
            $wrongTargetStore->retryDeadLetter(
                OutboxRecordId::fromString(self::RECORD_A),
                'operator-visible',
                'manual retry',
                $now,
            );
        } catch (DeferredTransportException $exception) {
            $wrongTargetFailure = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $wrongTargetFailure);
        self::assertSame('Outbox record is not dead-lettered.', $wrongTargetFailure->getMessage());
        self::assertSame(1, $wrongTargetConnection->closeCalls());

        $this->insertRecord(self::RECORD_GLOBAL, $this->operation(20), null);
        $staleClaim = $legacy->claimBatch('silent-relay', 1, $now, 60)[0];
        $legacy->markSent($staleClaim);
        $enabled = $this->relayStore($this->connection, $this->clock);
        $failed = false;
        try {
            $enabled->scheduleRetry($staleClaim, $now->modify('+1 minute'), self::FINGERPRINT);
        } catch (DeferredTransportException $exception) {
            $failed = true;
            self::assertSame('Outbox relay retry claim is stale.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        self::assertTrue($failed, 'a stale enabled target was accepted');
        self::assertSame('sent', $this->nativeState(self::RECORD_GLOBAL));
        self::assertSame(0, $this->auditCount());

        $enabledConnection = $this->faultConnection();
        $enabledClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:46:00.654321Z'));
        $enabled = $this->relayStore(
            $enabledConnection,
            $enabledClock,
            $this->auditBundle($enabledConnection, 1850, new DateTimeImmutable('2026-10-02T09:46:00.654321Z')),
        );
        $enabled->migrate();
        $enabledFirstId = $this->fixedId(0xe10);
        $enabledSecondId = $this->fixedId(0xe11);
        $enabled->insert($this->record($enabledFirstId, $this->operation(0xe10), null));
        $enabledClaim = $enabled->claimBatch('enabled-silent', 1, $now, 60)[0];
        $enabled->heartbeat($enabledClaim, $now, 60);
        $enabled->markSent($enabledClaim);
        $enabled->insert($this->record($enabledSecondId, $this->operation(0xe11), null));
        $enabledLease = $enabled->claimBatch('enabled-silent', 1, $now, 60)[0];
        $enabledReclaimed = $enabled->claimBatch('enabled-silent-next', 1, $now->modify('+61 seconds'), 60)[0];
        self::assertSame($enabledLease->recordId->toString(), $enabledReclaimed->recordId->toString());
        $enabled->markSent($enabledReclaimed);
        self::assertSame('sent', $this->nativeState($enabledFirstId));
        self::assertSame('sent', $this->nativeState($enabledSecondId));
        self::assertSame(0, $enabledClock->calls);
        self::assertSame(0, $enabledConnection->appendAttempts());
        self::assertSame(0, $this->auditCount());
    }

    public function testForeignAmbientAndPreBeginInputRejectionsDoNotCloseOrRollback(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $this->insertRecord(self::RECORD_A, self::OPERATION_BASE, null);
        $claim = $this->legacyStore($this->connection)->claimBatch('admission', 1, $now, 60)[0];

        $storeConnection = $this->faultConnection();
        $writerConnection = $this->faultConnection();
        $store = $this->relayStore(
            $storeConnection,
            new OutboxAuditClock(new DateTimeImmutable('2026-10-02T12:30:00.654321Z')),
            $this->auditBundle($writerConnection, 1200),
        );
        $failure = null;
        try {
            $store->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
        } catch (DeferredTransportException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertSame('Failed to schedule PostgreSQL outbox retry.', $failure->getMessage());
        self::assertSame(0, $storeConnection->beginCalls());
        self::assertSame(0, $storeConnection->rollbackCalls());
        self::assertSame(0, $storeConnection->closeCalls());

        $storeConnection->resetTrace();
        $invalid = null;
        try {
            $store->scheduleRetry($claim, $now->modify('+1 minute'), 'invalid');
        } catch (DeferredTransportException $exception) {
            $invalid = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $invalid);
        self::assertSame('Outbox failure fingerprint is invalid.', $invalid->getMessage());
        self::assertSame(0, $storeConnection->beginCalls());
        self::assertSame(0, $storeConnection->closeCalls());

        $ambient = $this->faultConnection();
        $ambientStore = $this->relayStore(
            $ambient,
            new OutboxAuditClock(new DateTimeImmutable('2026-10-02T12:31:00.654321Z')),
            $this->auditBundle($ambient, 1300),
        );
        $ambient->beginTransaction();
        $ambient->resetTrace();
        $ambientFailure = null;
        try {
            $ambientStore->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
        } catch (DeferredTransportException $exception) {
            $ambientFailure = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $ambientFailure);
        self::assertSame(0, $ambient->beginCalls());
        self::assertSame(0, $ambient->rollbackCalls());
        self::assertSame(0, $ambient->closeCalls());
        $ambient->rollBack();
        $ambient->close();
    }

    public function testNamedNativeAdmissionCasesLeaveEveryMutationPathUntouched(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $cases = ['foreign', 'autocommit-false', 'nested-root', 'manual-ambient', 'runtime-mismatch'];
        foreach ($cases as $index => $case) {
            $recordId = $this->fixedId(0xe30 + $index);
            $this->insertRecord($recordId, $this->operation(0xe30 + $index), null);
            $claim = null;
            if ($case === 'manual-ambient') {
                $claim = $this->legacyStore($this->connection)->claimBatch('named-admission', 1, $now, 60)[0];
                $this->legacyStore($this->connection)->moveToDeadLetter($claim, self::FINGERPRINT);
            } else {
                $claim = $this->legacyStore($this->connection)->claimBatch('named-admission', 1, $now, 60)[0];
            }
            /** @var PostgreSqlOutboxClaim $claim */
            $connection = $this->faultConnection();
            $clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T12:40:00.654321Z'));
            $writerConnection = $case === 'foreign' ? $this->faultConnection() : $connection;
            $store = $case === 'runtime-mismatch'
                ? $this->cliStore($connection, $clock, $this->auditBundle($connection, 1870 + $index))
                : $this->relayStore($connection, $clock, $this->auditBundle($writerConnection, 1870 + $index));
            if ($case === 'nested-root' || $case === 'manual-ambient') {
                $connection->beginTransaction();
                if ($case === 'nested-root') {
                    $connection->beginTransaction();
                }
            }
            $connection->resetTrace();
            if ($case === 'autocommit-false') {
                $connection->armPreEntryAutoCommitFalse();
            }
            $beforeAutoCommit = $connection->isAutoCommit();
            $beforeActive = $connection->isTransactionActive();
            $beforeNesting = $connection->getTransactionNestingLevel();
            $failure = null;
            try {
                if ($case === 'manual-ambient') {
                    $store = $this->cliStore($connection, $clock, $this->auditBundle($connection, 1880 + $index));
                    $store->retryDeadLetter(
                        OutboxRecordId::fromString($recordId),
                        'operator-visible',
                        'manual retry',
                        $now,
                    );
                } else {
                    $store->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
                }
            } catch (DeferredTransportException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(DeferredTransportException::class, $failure, $case . ' admission');
            self::assertSame(
                $case === 'manual-ambient'
                    ? 'Failed to retry dead-lettered outbox record.'
                    : 'Failed to schedule PostgreSQL outbox retry.',
                $failure->getMessage(),
                $case,
            );
            self::assertSame(0, $connection->beginCalls(), $case . ' begin');
            self::assertSame(0, $connection->rollbackCalls(), $case . ' rollback');
            self::assertSame(0, $connection->closeCalls(), $case . ' close');
            self::assertSame(0, $connection->nativeAttempts(), $case . ' native');
            self::assertSame(0, $connection->appendAttempts(), $case . ' C3');
            self::assertSame(0, $clock->calls, $case . ' clock');
            self::assertSame([], $connection->sqlTrace(), $case . ' SQL trace');
            self::assertSame($beforeAutoCommit, $connection->isAutoCommit(), $case . ' autoCommit state');
            self::assertSame($beforeActive, $connection->isTransactionActive(), $case . ' active state');
            self::assertSame($beforeNesting, $connection->getTransactionNestingLevel(), $case . ' nesting state');
            self::assertSame(0, $connection->commitCalls(), $case . ' commit');
            self::assertSame(
                $case === 'manual-ambient' ? 'dead_lettered' : 'leased',
                $this->nativeState($recordId),
                $case . ' native state',
            );
            if ($case === 'nested-root' || $case === 'manual-ambient') {
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $connection->close();
            }
        }
        self::assertSame(0, $this->auditCount());
    }

    public function testPersistentInvalidPostBeginStatesNeverRollbackOrReachNativeEffects(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $case = 0;
        foreach (['schedule', 'dead', 'manual'] as $root) {
            foreach (['autocommit', 'inactive', 'nested'] as $state) {
                ++$case;
                $recordId = $this->fixedId(0xe50 + $case);
                $this->insertRecord($recordId, $this->operation(0xe50 + $case), null);
                $connection = $this->faultConnection();
                $clock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T12:50:00.654321Z'));
                $store = $this->relayStore($connection, $clock, $this->auditBundle($connection, 1900 + $case));
                $claim = null;
                if ($root === 'manual') {
                    $legacy = $this->legacyStore($this->connection);
                    $claim = $legacy->claimBatch('persistent-state', 1, $now, 60)[0];
                    $legacy->moveToDeadLetter($claim, self::FINGERPRINT);
                    $store = $this->cliStore($connection, $clock, $this->auditBundle($connection, 1950 + $case));
                } else {
                    $claim = $store->claimBatch('persistent-state', 1, $now, 60)[0];
                }
                /** @var PostgreSqlOutboxClaim $claim */
                $connection->resetTrace();
                $connection->armPersistentPostBeginState($state);
                $failure = null;
                try {
                    $this->invokeRoot($root, $store, $claim, $recordId, $now);
                } catch (DeferredTransportException $exception) {
                    $failure = $exception;
                }
                self::assertInstanceOf(DeferredTransportException::class, $failure, $root . '/' . $state);
                self::assertSame(
                    match ($root) {
                        'schedule' => 'Failed to schedule PostgreSQL outbox retry.',
                        'dead' => 'Failed to dead-letter PostgreSQL outbox record.',
                        default => 'Failed to retry dead-lettered outbox record.',
                    },
                    $failure->getMessage(),
                    $root . '/' . $state,
                );
                self::assertSame(1, $connection->beginCalls(), $root . '/' . $state . ' begin');
                self::assertSame(0, $connection->rollbackCalls(), $root . '/' . $state . ' rollback');
                self::assertSame(1, $connection->closeCalls(), $root . '/' . $state . ' close');
                self::assertSame(0, $connection->nativeAttempts(), $root . '/' . $state . ' native');
                self::assertSame(0, $connection->appendAttempts(), $root . '/' . $state . ' C3');
                self::assertSame(0, $clock->calls, $root . '/' . $state . ' clock');
                self::assertSame([], $connection->sqlTrace(), $root . '/' . $state . ' SQL trace');
                self::assertSame(
                    $root === 'manual' ? 'dead_lettered' : 'leased',
                    $this->nativeState($recordId),
                    $root . '/' . $state . ' state',
                );
                self::assertTrue($connection->postBeginStateCalls() > 0, $root . '/' . $state . ' state probe');
                self::assertTrue($connection->isClosed(), $root . '/' . $state . ' disconnected');
            }
        }
        self::assertSame(0, $this->auditCount());
    }

    public function testActualRelayRuntimeContinuesAfterStaleSettlementAndKeepsRetryDeadLetterCounters(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $staleFirst = self::RECORD_RUNTIME_STALE;
        $staleSecond = self::RECORD_RUNTIME_SENT;
        $this->insertRecord($staleFirst, $this->operation(31), null);
        $this->insertRecord($staleSecond, $this->operation(32), $this->tenant(self::TENANT_A));
        $staleConnection = $this->faultConnection();
        $staleClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:00:00.654321Z'));
        $staleStore = $this->relayStore(
            $staleConnection,
            $staleClock,
            $this->auditBundle($staleConnection, 900, new DateTimeImmutable('2026-10-02T09:00:00.654321Z')),
        );
        $staleConnection->armAppendFaultAt(1);
        $runtime = new OutboxRelayRuntime(
            $staleStore,
            new GenericOutboxRelaySender([$this->operation(31)]),
            new OutboxRelayConfiguration('o3-stale', batchSize: 2, leaseSeconds: 3600, maxAttempts: 2),
            $staleClock,
        );
        $result = $runtime->runBatch($now);
        self::assertSame(1, $staleConnection->appendAttempts());
        self::assertSame(1, $staleConnection->appendWrites());
        self::assertTrue($staleConnection->isClosed());
        self::assertFalse($staleConnection->isTransactionActive());
        self::assertSame(2, $result->claimed);
        self::assertSame(1, $result->sent);
        self::assertSame(0, $result->retried);
        self::assertSame(0, $result->deadLettered);
        self::assertSame(1, $result->stale);
        self::assertSame('leased', $this->nativeState($staleFirst));
        self::assertSame('sent', $this->nativeState($staleSecond));
        self::assertSame(0, $this->auditCount());

        $deadStaleFirst = self::RECORD_RUNTIME_DEAD_STALE;
        $deadStaleSecond = self::RECORD_RUNTIME_DEAD_SENT;
        $this->insertRecord($deadStaleFirst, $this->operation(35), null);
        $this->insertRecord($deadStaleSecond, $this->operation(36), $this->tenant(self::TENANT_B));
        $deadStaleConnection = $this->faultConnection();
        $deadStaleClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:01:00.654321Z'));
        $deadStaleStore = $this->relayStore(
            $deadStaleConnection,
            $deadStaleClock,
            $this->auditBundle($deadStaleConnection, 910, new DateTimeImmutable('2026-10-02T09:01:00.654321Z')),
        );
        $deadStaleConnection->armAppendFaultAt(1);
        $deadStaleRuntime = new OutboxRelayRuntime(
            $deadStaleStore,
            new GenericOutboxRelaySender([$this->operation(35)]),
            new OutboxRelayConfiguration('o3-dead-stale', batchSize: 2, leaseSeconds: 3600, maxAttempts: 1),
            $deadStaleClock,
        );
        $deadStaleResult = $deadStaleRuntime->runBatch($now->modify('+1 minute'));
        self::assertSame(1, $deadStaleConnection->appendAttempts());
        self::assertSame(1, $deadStaleConnection->appendWrites());
        self::assertTrue($deadStaleConnection->isClosed());
        self::assertFalse($deadStaleConnection->isTransactionActive());
        self::assertSame(2, $deadStaleResult->claimed);
        self::assertSame(1, $deadStaleResult->sent);
        self::assertSame(0, $deadStaleResult->retried);
        self::assertSame(0, $deadStaleResult->deadLettered);
        self::assertSame(1, $deadStaleResult->stale);
        self::assertSame('leased', $this->nativeState($deadStaleFirst));
        self::assertSame('sent', $this->nativeState($deadStaleSecond));
        self::assertSame(0, $this->auditCount());
        self::assertSame(1, $deadStaleConnection->closeCalls());

        $retryRecord = self::RECORD_RUNTIME_RETRY;
        $this->insertRecord($retryRecord, $this->operation(33), null);
        $retryConnection = $this->faultConnection();
        $retryClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:02:00.654321Z'));
        $retryStore = $this->relayStore(
            $retryConnection,
            $retryClock,
            $this->auditBundle($retryConnection, 920, new DateTimeImmutable('2026-10-02T09:02:00.654321Z')),
        );
        $retryRuntime = new OutboxRelayRuntime(
            $retryStore,
            new GenericOutboxRelaySender([$this->operation(33)]),
            new OutboxRelayConfiguration('o3-threshold', batchSize: 1, maxAttempts: 2),
            $retryClock,
        );
        $first = $retryRuntime->runBatch($now->modify('+2 minutes'));
        self::assertSame(1, $first->retried);
        self::assertSame(0, $first->deadLettered);
        self::assertSame('retry_scheduled', $this->nativeState($retryRecord));
        self::assertSame(1, $this->auditCount());
        $second = $retryRuntime->runBatch($now->modify('+2 minutes 2 seconds'));
        self::assertSame(0, $second->retried);
        self::assertSame(1, $second->deadLettered);
        self::assertSame('dead_lettered', $this->nativeState($retryRecord));
        self::assertSame(2, $this->auditCount());
        self::assertSame(2, $retryConnection->appendAttempts());
        self::assertSame(2, $retryConnection->appendWrites());
        self::assertFalse($retryConnection->isTransactionActive());

        $cleanupRecord = self::RECORD_RUNTIME_CLEANUP;
        $cleanupSecond = self::RECORD_RUNTIME_CLEANUP_SENT;
        $this->insertRecord($cleanupRecord, $this->operation(34), null);
        $this->insertRecord($cleanupSecond, $this->operation(37), $this->tenant(self::TENANT_A));
        $cleanupConnection = $this->faultConnection();
        $cleanupClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T09:04:00.654321Z'));
        $cleanupStore = $this->relayStore(
            $cleanupConnection,
            $cleanupClock,
            $this->auditBundle($cleanupConnection, 940, new DateTimeImmutable('2026-10-02T09:04:00.654321Z')),
        );
        $cleanupConnection->armAppendFaultAt(1);
        $cleanupConnection->armCloseFault();
        $cleanupRuntime = new OutboxRelayRuntime(
            $cleanupStore,
            new GenericOutboxRelaySender([$this->operation(34)]),
            new OutboxRelayConfiguration('o3-cleanup', batchSize: 2, leaseSeconds: 3600),
            $cleanupClock,
        );
        $cleanupResult = $cleanupRuntime->runBatch($now->modify('+4 minutes'));
        self::assertSame(1, $cleanupConnection->appendAttempts());
        self::assertSame(1, $cleanupConnection->appendWrites());
        self::assertTrue($cleanupConnection->isClosed());
        self::assertFalse($cleanupConnection->isTransactionActive());
        self::assertSame(2, $cleanupResult->claimed);
        self::assertSame(1, $cleanupResult->sent);
        self::assertSame(1, $cleanupResult->stale);
        self::assertSame(0, $cleanupResult->retried);
        self::assertSame(0, $cleanupResult->deadLettered);
        self::assertSame('leased', $this->nativeState($cleanupRecord));
        self::assertSame('sent', $this->nativeState($cleanupSecond));
        self::assertSame(2, $this->auditCount());
        self::assertSame(1, $cleanupConnection->closeCalls());
    }

    private function invokeRoot(
        string $root,
        PostgreSqlOutboxStore $store,
        PostgreSqlOutboxClaim $claim,
        string $recordId,
        DateTimeImmutable $now,
    ): void {
        switch ($root) {
            case 'schedule':
                $store->scheduleRetry($claim, $now->modify('+1 minute'), self::FINGERPRINT);
                return;
            case 'dead':
                $store->moveToDeadLetter($claim, self::FINGERPRINT);
                return;
            case 'manual':
                $store->retryDeadLetter(
                    OutboxRecordId::fromString($recordId),
                    'operator-visible',
                    'manual retry',
                    $now,
                );
                return;
            default:
                throw new \LogicException('unknown native root');
        }
    }

    private function assertCommitAfterStaleAndLaterCycle(
        GenericOutboxFaultConnection $connection,
        string $root,
        PostgreSqlOutboxStore $store,
        PostgreSqlOutboxClaim $claim,
        string $recordId,
        DateTimeImmutable $now,
        int $case,
    ): void {
        $beforeAudit = $this->auditCount();
        $beforeAction = $this->actionIdForRecord($recordId);
        $beforeIngress = $this->durableIngressForNativeRecord($recordId);
        self::assertNotEmpty($beforeIngress, $root . '/commit-after durable ingress before');
        $connection->resetTrace();
        $failure = null;
        try {
            $this->invokeRoot($root, $store, $claim, $recordId, $now);
        } catch (DeferredTransportException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure, $root . '/commit-after stale');
        self::assertSame(
            match ($root) {
                'schedule' => 'Outbox relay retry claim is stale.',
                'dead' => 'Outbox relay dead-letter claim is stale.',
                default => 'Outbox record is not dead-lettered.',
            },
            $failure->getMessage(),
            $root . '/commit-after stale message',
        );
        self::assertSame($beforeAudit, $this->auditCount(), $root . '/commit-after stale ingress');
        self::assertSame(0, $connection->appendAttempts(), $root . '/commit-after stale C3');
        self::assertSame(0, $connection->appendWrites(), $root . '/commit-after stale C3 writes');

        $laterClock = new OutboxAuditClock(new DateTimeImmutable('2026-10-02T10:01:00.654321Z'));
        if ($root === 'dead') {
            $later = $this->cliStore(
                $connection,
                $laterClock,
                $this->auditBundle($connection, 2100 + $case, new DateTimeImmutable('2026-10-02T10:01:00.654321Z')),
            );
            $later->retryDeadLetter(
                OutboxRecordId::fromString($recordId),
                'operator-visible-later',
                'manual retry later',
                $now->modify('+61 seconds'),
            );
        } else {
            $laterClaim = $this->legacyStore($connection)->claimBatch(
                'later-cycle-' . $case,
                1,
                $now->modify('+61 seconds'),
                60,
            )[0];
            self::assertSame($recordId, $laterClaim->recordId->toString(), $root . '/later native claim identity');
            $later = $this->relayStore(
                $connection,
                $laterClock,
                $this->auditBundle($connection, 2100 + $case, new DateTimeImmutable('2026-10-02T10:01:00.654321Z')),
            );
            $later->scheduleRetry($laterClaim, $now->modify('+2 minutes'), self::FINGERPRINT);
        }
        $afterAction = $this->actionIdForRecord($recordId);
        $afterIngress = $this->durableIngressForNativeRecord($recordId);
        self::assertNotSame($beforeAction, $afterAction, $root . '/commit-after distinct action');
        self::assertCount(count($beforeIngress) + 1, $afterIngress, $root . '/commit-after durable key count');
        self::assertNotSame(
            $beforeIngress[count($beforeIngress) - 1]['actionId'],
            $afterIngress[count($afterIngress) - 1]['actionId'],
            $root . '/commit-after durable key',
        );
        self::assertNotSame(
            $beforeIngress[count($beforeIngress) - 1]['idempotencyKeyHash'],
            $afterIngress[count($afterIngress) - 1]['idempotencyKeyHash'],
            $root . '/commit-after durable hash',
        );
        self::assertNotSame(
            $beforeIngress[count($beforeIngress) - 1]['auditRecordId'],
            $afterIngress[count($afterIngress) - 1]['auditRecordId'],
            $root . '/commit-after audit row identity',
        );
        self::assertSame($beforeAudit + 1, $this->auditCount(), $root . '/commit-after later ingress');
        self::assertSame('retry_scheduled', $this->nativeState($recordId), $root . '/commit-after later state');
        $this->probe->executeStatement('UPDATE "'
        . self::SCHEMA
        . '"."outbox_records" SET next_attempt_at=:future WHERE record_id=:id', [
            'future' => $now->modify('+1 hour')->format('Y-m-d H:i:s.uP'),
            'id' => $recordId,
        ]);
    }

    private function actionIdForRecord(string $recordId): string
    {
        $resourceId = (string) new HmacAuditOpaqueIdDeriver(str_repeat('a', 32))->derive(
            AuditOpaqueIdPurpose::Resource,
            'generic-outbox:record:' . $recordId,
        );
        $actionId = null;
        foreach ($this->logicalOutbox() as $event) {
            if (($event['resource']['id'] ?? null) === $resourceId) {
                $actionId = (string) $event['action']['id'];
            }
        }
        self::assertIsString($actionId, 'a durable action was not found for ' . $recordId);

        return $actionId;
    }

    private function seedCommittedSentinel(GenericOutboxFaultConnection $connection, int $case): string
    {
        $recordId = $this->fixedId(0xa00 + $case);
        $sentinelNow = new DateTimeImmutable('2026-10-02T09:00:00.654321Z');
        $legacy = $this->legacyStore($connection);
        $legacy->insert($this->record($recordId, $this->operation(0xa00 + $case), null));
        $clock = new OutboxAuditClock($sentinelNow);
        $store = $this->relayStore($connection, $clock, $this->auditBundle($connection, 1600 + $case, $sentinelNow));
        $claim = $store->claimBatch('sentinel-' . $case, 1, $sentinelNow, 60)[0];
        self::assertSame($recordId, $claim->recordId->toString(), 'sentinel claim identity');
        $store->scheduleRetry($claim, $sentinelNow->modify('+1 hour'), self::FINGERPRINT);

        return $recordId;
    }

    private function armFault(GenericOutboxFaultConnection $connection, string $fault): void
    {
        switch ($fault) {
            case 'begin-before':
                $connection->armBeginFault('before');
                return;
            case 'begin-after':
                $connection->armBeginFault('after');
                return;
            case 'post-begin-autocommit':
                $connection->armPostBeginState('autocommit');
                return;
            case 'post-begin-inactive':
                $connection->armPostBeginState('inactive');
                return;
            case 'post-begin-nested':
                $connection->armPostBeginState('nested');
                return;
            case 'native-before':
                $connection->armNativeFault('before');
                return;
            case 'native-malformed':
                $connection->armMalformedNativeRow();
                return;
            case 'c3-before':
                $connection->armC3Fault('before');
                return;
            case 'c3-after':
                $connection->armAppendFaultAt(1);
                return;
            case 'commit-before':
                $connection->armCommitFault('before');
                return;
            case 'commit-after':
                $connection->armCommitFault('after');
                return;
            case 'probe-after-begin':
                $connection->armProbeFaultAfterBegin();
                return;
            case 'cleanup-probe':
                $connection->armAppendFaultAt(1);
                $connection->armCleanupProbeFault();
                return;
            case 'rollback-before':
                $connection->armAppendFaultAt(1);
                $connection->armRollbackFault('before');
                return;
            case 'rollback-after':
                $connection->armAppendFaultAt(1);
                $connection->armRollbackFault('after');
                return;
            case 'close':
                $connection->armAppendFaultAt(1);
                $connection->armCloseFault();
                return;
            default:
                throw new \LogicException('unknown injected fault');
        }
    }

    private function assertFaultReached(GenericOutboxFaultConnection $connection, string $fault, string $root): void
    {
        $reached = match ($fault) {
            'begin-before', 'begin-after' => $connection->beginCalls() === 1,
            'post-begin-autocommit', 'post-begin-inactive', 'post-begin-nested' => $connection->postBeginStateCalls()
                === 1,
            'native-before' => $connection->nativeAttempts() === 1,
            'native-malformed' => $connection->nativeAttempts() === ($root === 'manual' ? 2 : 1),
            'c3-before',
            'c3-after',
            'rollback-before',
            'rollback-after',
            'close',
            'cleanup-probe',
                => $connection->appendAttempts() === 1,
            'commit-before', 'commit-after' => $connection->commitCalls() === 1,
            'probe-after-begin' => $connection->probeFaults() === 1,
            default => false,
        };
        self::assertTrue($reached, $root . '/' . $fault . ' injection was not reached');
        if ($fault === 'native-malformed') {
            self::assertSame(
                1,
                $connection->nativeMalformedOverrideApplications(),
                $root . '/' . $fault . ' malformed RETURNING consumption',
            );
        }
        if (in_array($fault, ['probe-after-begin', 'cleanup-probe'], true)) {
            self::assertSame(1, $connection->probeFaults(), $root . '/' . $fault . ' probe fault trace');
        }
        self::assertSame(
            match ($fault) {
                'c3-after',
                'commit-before',
                'commit-after',
                'rollback-before',
                'rollback-after',
                'close',
                'cleanup-probe',
                    => 1,
                default => 0,
            },
            $connection->appendWrites(),
            $root . '/' . $fault . ' C3 write trace',
        );
    }

    private function assertSqlTraceContains(
        GenericOutboxFaultConnection $connection,
        string $needle,
        string $message,
    ): void {
        foreach ($connection->sqlTrace() as $statement) {
            if (str_contains($statement, $needle)) {
                return;
            }
        }
        self::fail($message . ': ' . json_encode($connection->sqlTrace(), JSON_THROW_ON_ERROR));
    }

    private function assertSensitiveStoreFrame(
        Throwable $failure,
        string $function,
        string $marker,
        int $argumentCount,
    ): void {
        $matches = [];
        foreach ($failure->getTrace() as $frame) {
            if (
                ($frame['class'] ?? null) !== PostgreSqlOutboxStore::class
                || ($frame['function'] ?? null) !== $function
            ) {
                continue;
            }
            $matches[] = $frame;
        }
        self::assertCount(1, $matches, $function . ' owned frame');
        $args = $matches[0]['args'] ?? null;
        self::assertIsArray($args, $function . ' arguments are present');
        self::assertCount($argumentCount, $args, $function . ' argument count');
        foreach ($args as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument, $function . ' argument redaction');
        }
        self::assertStringNotContainsString($marker, print_r($failure, true), $function . ' debug surface');
        self::assertStringNotContainsString($marker, var_export($failure, true), $function . ' export surface');
        $serialized = null;
        try {
            $serialized = serialize($failure);
        } catch (Throwable) {
        }
        if (is_string($serialized)) {
            self::assertStringNotContainsString($marker, $serialized, $function . ' serialized surface');
        }
    }

    private function fixedId(int $suffix): string
    {
        return sprintf('019fca20-0000-7000-8000-%012x', $suffix);
    }

    private function assertOwnedFailure(
        PostgreSqlOutboxStore $store,
        PostgreSqlOutboxClaim $claim,
        GenericOutboxFaultConnection $connection,
        OutboxAuditClock $clock,
        int $expectedCommitCalls,
        int $expectedRollbackCalls,
        int $expectedCloseCalls,
    ): void {
        $failure = null;
        try {
            $store->scheduleRetry($claim, new DateTimeImmutable(self::NOW), self::FINGERPRINT);
            self::fail('the injected Outbox fault was not reached');
        } catch (DeferredTransportException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(DeferredTransportException::class, $failure);
        self::assertSame('Failed to schedule PostgreSQL outbox retry.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertStringNotContainsString(self::C3_CANARY, $failure->getMessage());
        self::assertSame($expectedCommitCalls, $connection->commitCalls());
        self::assertSame($expectedRollbackCalls, $connection->rollbackCalls());
        self::assertSame($expectedCloseCalls, $connection->closeCalls());
        self::assertSame(1, $connection->appendAttempts());
        self::assertSame(1, $clock->calls);
    }

    private function relayStore(
        Connection $connection,
        OutboxAuditClock $clock,
        ?array $bundle = null,
    ): PostgreSqlOutboxStore {
        $bundle ??= $this->auditBundle($connection);
        $writer = $this->writer($bundle, $clock, AuditSourceRuntime::Relay);

        return new PostgreSqlOutboxStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            new ReflectionJsonOperationCodec(),
            self::SCHEMA,
            new GenericOutboxAuditHook($writer),
        );
    }

    private function cliStore(
        Connection $connection,
        OutboxAuditClock $clock,
        ?array $bundle = null,
    ): PostgreSqlOutboxStore {
        $bundle ??= $this->auditBundle($connection, 500);
        $writer = $this->writer($bundle, $clock, AuditSourceRuntime::Cli);

        return new PostgreSqlOutboxStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            new ReflectionJsonOperationCodec(),
            self::SCHEMA,
            new GenericOutboxAuditHook($writer),
        );
    }

    private function legacyStore(Connection $connection): PostgreSqlOutboxStore
    {
        return new PostgreSqlOutboxStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            new ReflectionJsonOperationCodec(),
            self::SCHEMA,
        );
    }

    /** @param array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator,protection:AuditProtectedBodyCodec} $bundle */
    private function writer(
        array $bundle,
        OutboxAuditClock $clock,
        AuditSourceRuntime $runtime,
    ): GenericOutboxAuditWriter {
        return new GenericOutboxAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $clock,
            new ActorContext(
                new ActorRef('historical-origin', 'origin'),
                new ActorRef('authorized-operator', 'operator'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => '019fca20-0000-7000-8000-000000000299']),
            $runtime,
            self::BUILD_ID,
        );
    }

    /** @return array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator,protection:AuditProtectedBodyCodec} */
    private function auditBundle(
        Connection $connection,
        int $uuidOffset = 0,
        ?DateTimeImmutable $recordedAt = null,
    ): array {
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
            new GenericOutboxAuditKeySource(),
            new GenericOutboxAuditNonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $ingress,
        );
        $storeClock = new OutboxAuditClock($recordedAt ?? new DateTimeImmutable('2026-10-02T09:00:00.654321Z'));
        $uuids = [];
        for ($index = 1; $index <= 64; ++$index) {
            $uuids[] = sprintf('019fca20-0000-7000-8000-%012x', 0x300 + $uuidOffset + $index);
        }
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $this->auditSchema,
            $storeClock,
            new GenericOutboxAuditUuidGenerator($uuids),
            $validator,
            $finalizer,
            $partitions,
            $recordsCodec,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore(
            $connection,
            $this->auditOutboxSchema,
            $storeClock,
            new GenericOutboxAuditUuidGenerator($uuids),
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

    private function insertRecord(string $recordId, string $operationId, ?TenantRef $tenant): void
    {
        $this->legacyStore($this->connection)->insert($this->record($recordId, $operationId, $tenant));
    }

    private function record(string $recordId, string $operationId, ?TenantRef $tenant): PostgreSqlOutboxRecord
    {
        return new PostgreSqlOutboxRecord(
            OutboxRecordId::fromString($recordId),
            OperationId::fromString($operationId),
            'mail.send',
            1,
            '{"address":"opaque"}',
            $this->contextJson($operationId, $tenant),
            new DateTimeImmutable(self::NOW),
            new DateTimeImmutable(self::NOW),
            'app',
            tenant: $tenant,
        );
    }

    private function contextJson(string $operationId, ?TenantRef $tenant): string
    {
        return json_encode([
            'operation_id' => $operationId,
            'received_at' => self::NOW,
            'correlation_id' => '019fca20-0000-7000-8000-000000000299',
            'causation_id' => null,
            'attempt' => null,
            'deadline' => null,
            'actors' => null,
            'idempotency_key_hash' => null,
            'schedule' => null,
            ...($tenant === null ? [] : ['tenant' => ['type' => $tenant->type(), 'id' => $tenant->id()]]),
        ], JSON_THROW_ON_ERROR);
    }

    private function tenant(string $id): TenantRef
    {
        return new TenantRef('account', $id);
    }

    private function operation(int $number): string
    {
        return sprintf('019fca20-0000-7000-8000-%012x', 0x200 + $number);
    }

    private function nativeState(string $recordId): string
    {
        return (string) $this->probe->fetchOne('SELECT state FROM "'
        . self::SCHEMA
        . '"."outbox_records" WHERE record_id=:id', ['id' => $recordId]);
    }

    private function auditCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."audit_outbox_records"');
    }

    /** @return array<string,mixed> */
    private function durableAuditRow(int $index): array
    {
        $rows = $this->probe->fetchAllAssociative('SELECT record_id, partition_id, ingress_ordinal, idempotency_key_hash
             FROM "'
        . self::SCHEMA
        . '"."audit_outbox_records" ORDER BY ingress_ordinal');
        self::assertArrayHasKey($index, $rows);
        /** @var array<string,mixed> $row */
        $row = $rows[$index];

        return $row;
    }

    /** @return list<array{auditRecordId:string,idempotencyKeyHash:string,actionId:string}> */
    private function durableIngressForNativeRecord(string $recordId): array
    {
        $resourceId = (string) new HmacAuditOpaqueIdDeriver(str_repeat('a', 32))->derive(
            AuditOpaqueIdPurpose::Resource,
            'generic-outbox:record:' . $recordId,
        );
        $rows = $this->probe->fetchAllAssociative('SELECT record_id, partition_id, tenant_type, tenant_id, ingress_ordinal, idempotency_key_hash,
                    content_hash, protected_body
             FROM "'
        . self::SCHEMA
        . '"."audit_outbox_records" ORDER BY ingress_ordinal');
        $matches = [];
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
            $logical = $this->auditProtection->unprotectOutbox($ingress)->toArray()['logicalRecord'];
            if (($logical['resource']['id'] ?? null) !== $resourceId) {
                continue;
            }
            $matches[] = [
                'auditRecordId' => (string) $row['record_id'],
                'idempotencyKeyHash' => (string) $row['idempotency_key_hash'],
                'actionId' => (string) $logical['action']['id'],
            ];
        }

        return $matches;
    }

    /** @param array<string,mixed> $event @param array<string,mixed> $row */
    private function assertDurableIdempotencyKey(array $event, array $row): void
    {
        $tenant = $event['tenant'] ?? null;
        $tenantReference = is_array($tenant)
            ? new AuditTypedReference((string) ($tenant['type'] ?? ''), (string) ($tenant['id'] ?? ''))
            : null;
        $partition = new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenantReference);
        $expected = new AuditOutboxIdempotencyKeyHasher()->hash(
            $partition,
            'generic-outbox:' . (string) $event['action']['id'],
        );
        self::assertSame((string) $row['partition_id'], $partition);
        self::assertSame($expected, (string) $row['idempotency_key_hash']);
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
            $logical[] = $this->auditProtection->unprotectOutbox($ingress)->toArray()['logicalRecord'];
        }

        return $logical;
    }

    /** @param array<string,mixed> $event */
    private function assertOutboxEvent(
        array $event,
        string $type,
        AuditSourceRuntime $runtime,
        ?TenantRef $tenant,
        string $recordId,
        ?string $stateVersion,
        ?string $retryAuditId = null,
    ): void {
        $definition = $type === 'outbox.delivery.retry_scheduled'
            ? ['phase' => 'committed', 'status' => 'unknown', 'code' => 'outbox_retry_scheduled']
            : (
                $type === 'outbox.delivery.dead_lettered'
                    ? ['phase' => 'failed', 'status' => 'failed', 'code' => 'outbox_dead_lettered']
                    : ['phase' => 'requested', 'status' => 'unknown', 'code' => 'outbox_retry_requested']
            );
        self::assertSame($type, $event['event']['type']);
        self::assertSame('outbox', $event['event']['category']);
        self::assertSame('outbox_record', $event['resource']['type']);
        self::assertSame(
            $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()],
            $event['tenant'],
        );
        self::assertSame($definition['phase'], $event['action']['phase']);
        self::assertSame($definition['status'], $event['outcome']['status']);
        self::assertSame($definition['code'], $event['outcome']['code']);
        self::assertSame('blackops.outbox.delivery', $event['policy']['id']);
        self::assertSame($definition['code'], $event['policy']['reasonCode']);
        self::assertSame('durable', $event['policy']['auditDelivery']);
        self::assertSame(
            [
                'owner' => 'framework',
                'component' => 'blackops.outbox.delivery',
                'runtime' => $runtime->value,
                'buildId' => self::BUILD_ID,
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
        self::assertSame(['correlationId' => '019fca20-0000-7000-8000-000000000299'], $event['correlation']);
        self::assertSame(['version' => 1], $event['evidence']);
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        self::assertSame(
            (string) $deriver->derive(AuditOpaqueIdPurpose::Resource, 'generic-outbox:record:' . $recordId),
            $event['resource']['id'],
        );
        $context = $retryAuditId === null
            ? 'generic-outbox:' . $type . ':' . $recordId . ':' . $stateVersion
            : 'generic-outbox:outbox.delivery.dead_letter_retry_requested:' . $recordId . ':' . $retryAuditId;
        self::assertSame((string) $deriver->derive(AuditOpaqueIdPurpose::Action, $context), $event['action']['id']);
    }

    private function claimWithProvenance(PostgreSqlOutboxClaim $claim, TenantRef $tenant): PostgreSqlOutboxClaim
    {
        $message = $claim->message;
        return new PostgreSqlOutboxClaim(
            $claim->recordId,
            new DeferredOperationMessage(
                OperationId::fromString(self::FORGED_OPERATION_ID),
                $message->operationType(),
                $message->schemaVersion(),
                json_encode(['marker' => self::FORGED_PAYLOAD_MARKER], JSON_THROW_ON_ERROR),
                json_encode([
                    'operation_id' => self::FORGED_OPERATION_ID,
                    'received_at' => self::NOW,
                    'correlation_id' => self::FORGED_CORRELATION_ID,
                    'causation_id' => null,
                    'attempt' => null,
                    'deadline' => null,
                    'actors' => [
                        'origin' => ['type' => 'forged-origin', 'id' => self::FORGED_ACTOR_ID],
                    ],
                    'idempotency_key_hash' => null,
                    'schedule' => null,
                ], JSON_THROW_ON_ERROR),
                $message->availableAt(),
                $tenant,
                new ActorRef(self::FORGED_ACTOR_ID, 'forged-origin'),
            ),
            $claim->relayId,
            $claim->fencingToken,
            $claim->attemptCount,
            $claim->leaseExpiresAt,
        );
    }

    private function faultConnection(): GenericOutboxFaultConnection
    {
        $connection = new GenericOutboxFaultConnection($this->connection->getParams(), $this->connection->getDriver());
        $this->faultConnections[] = $connection;

        return $connection;
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

final class OutboxAuditClock implements ClockInterface
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

final class GenericOutboxAuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'generic-outbox-audit-' . $this->calls, true), 0, 24);
    }
}

final class GenericOutboxAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return $this->key('o3-' . $purpose->value, $tenant, $purpose);
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

final class GenericOutboxAuditUuidGenerator implements Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('generic outbox audit UUID sequence exhausted.');
        }

        return $id;
    }
}

final class GenericOutboxFaultConnection extends Connection
{
    private int $appendAttempts = 0;
    private int $appendWrites = 0;
    private ?int $appendFaultAt = null;
    private ?string $beginFault = null;
    private ?string $nativeFault = null;
    private ?string $c3Fault = null;
    private ?string $commitFault = null;
    private ?string $rollbackFault = null;
    private ?string $postBeginState = null;
    private ?string $persistentPostBeginState = null;
    private bool $preEntryAutoCommitFalse = false;
    private bool $probeAfterBegin = false;
    private bool $cleanupProbeFault = false;
    private bool $closeFault = false;
    /** @var array<string,mixed>|null */
    private ?array $nativeRowOverride = null;
    private int $begins = 0;
    private int $nativeAttempts = 0;
    private int $nativeMalformedOverrideApplications = 0;
    private int $postBeginStateCalls = 0;
    private int $probeCalls = 0;
    private int $probeFaults = 0;
    private int $commits = 0;
    private int $rollbacks = 0;
    private int $closes = 0;
    /** @var list<string> */
    private array $sqlStatements = [];
    private bool $closed = false;

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function armAppendFaultAt(int $number): void
    {
        $this->appendFaultAt = $number;
    }

    public function armBeginFault(string $mode): void
    {
        $this->beginFault = $mode;
    }

    public function armNativeFault(string $mode): void
    {
        $this->nativeFault = $mode;
    }

    public function armMalformedNativeRow(): void
    {
        $this->nativeRowOverride = ['record_id' => 'malformed'];
    }

    public function armC3Fault(string $mode): void
    {
        $this->c3Fault = $mode;
    }

    public function armCommitFault(string $mode): void
    {
        $this->commitFault = $mode;
    }

    public function armRollbackFault(string $mode): void
    {
        $this->rollbackFault = $mode;
    }

    public function armPostBeginState(string $state): void
    {
        $this->postBeginState = $state;
    }

    public function armPersistentPostBeginState(string $state): void
    {
        $this->persistentPostBeginState = $state;
    }

    public function armPreEntryAutoCommitFalse(): void
    {
        $this->preEntryAutoCommitFalse = true;
    }

    public function armProbeFaultAfterBegin(): void
    {
        $this->probeAfterBegin = true;
    }

    public function armCleanupProbeFault(): void
    {
        $this->cleanupProbeFault = true;
    }

    public function armCloseFault(): void
    {
        $this->closeFault = true;
    }

    public function appendAttempts(): int
    {
        return $this->appendAttempts;
    }

    public function appendWrites(): int
    {
        return $this->appendWrites;
    }

    public function beginCalls(): int
    {
        return $this->begins;
    }

    public function nativeAttempts(): int
    {
        return $this->nativeAttempts;
    }

    public function nativeMalformedOverrideApplications(): int
    {
        return $this->nativeMalformedOverrideApplications;
    }

    public function postBeginStateCalls(): int
    {
        return $this->postBeginStateCalls;
    }

    public function probeCalls(): int
    {
        return $this->probeCalls;
    }

    public function probeFaults(): int
    {
        return $this->probeFaults;
    }

    public function commitCalls(): int
    {
        return $this->commits;
    }

    public function rollbackCalls(): int
    {
        return $this->rollbacks;
    }

    public function closeCalls(): int
    {
        return $this->closes;
    }

    /** @return list<string> */
    public function sqlTrace(): array
    {
        return $this->sqlStatements;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function resetTrace(): void
    {
        $this->appendAttempts = 0;
        $this->appendWrites = 0;
        $this->begins = 0;
        $this->nativeAttempts = 0;
        $this->nativeMalformedOverrideApplications = 0;
        $this->postBeginStateCalls = 0;
        $this->probeCalls = 0;
        $this->probeFaults = 0;
        $this->commits = 0;
        $this->rollbacks = 0;
        $this->closes = 0;
        $this->sqlStatements = [];
        $this->appendFaultAt = null;
        $this->beginFault = null;
        $this->nativeFault = null;
        $this->c3Fault = null;
        $this->commitFault = null;
        $this->rollbackFault = null;
        $this->postBeginState = null;
        $this->persistentPostBeginState = null;
        $this->preEntryAutoCommitFalse = false;
        $this->probeAfterBegin = false;
        $this->cleanupProbeFault = false;
        $this->closeFault = false;
        $this->nativeRowOverride = null;
    }

    public function beginTransaction(): void
    {
        ++$this->begins;
        $fault = $this->beginFault;
        $this->beginFault = null;
        if ($fault === 'before') {
            throw new \RuntimeException('generic outbox begin before');
        }
        parent::beginTransaction();
        if ($fault === 'after') {
            throw new \RuntimeException('generic outbox begin after');
        }
    }

    public function isAutoCommit(): bool
    {
        if ($this->preEntryAutoCommitFalse && $this->begins === 0) {
            return false;
        }
        if ($this->persistentPostBeginState === 'autocommit' && $this->begins > 0) {
            ++$this->postBeginStateCalls;
            return false;
        }
        if ($this->postBeginState === 'autocommit' && $this->begins > 0) {
            $this->postBeginState = null;
            ++$this->postBeginStateCalls;
            return false;
        }

        return parent::isAutoCommit();
    }

    public function isTransactionActive(): bool
    {
        if ($this->persistentPostBeginState === 'inactive' && $this->begins > 0) {
            ++$this->postBeginStateCalls;
            return false;
        }
        if ($this->postBeginState === 'inactive' && $this->begins > 0) {
            $this->postBeginState = null;
            ++$this->postBeginStateCalls;
            return false;
        }

        return parent::isTransactionActive();
    }

    public function getTransactionNestingLevel(): int
    {
        ++$this->probeCalls;
        $level = parent::getTransactionNestingLevel();
        if ($this->persistentPostBeginState === 'nested' && $this->begins > 0 && $level === 1) {
            ++$this->postBeginStateCalls;
            return 2;
        }
        if ($this->postBeginState === 'nested' && $this->begins > 0 && $level === 1) {
            $this->postBeginState = null;
            ++$this->postBeginStateCalls;
            return 2;
        }
        if ($this->probeAfterBegin && $this->begins > 0 && $level === 1) {
            $this->probeAfterBegin = false;
            ++$this->probeFaults;
            throw new \RuntimeException('generic outbox active-root probe');
        }
        if ($this->cleanupProbeFault && $this->appendAttempts > 0 && $level === 1) {
            $this->cleanupProbeFault = false;
            ++$this->probeFaults;
            throw new \RuntimeException('generic outbox cleanup probe');
        }

        return $level;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->traceSql($query);
        $native = $this->isNativeOutboxQuery($query);
        if (!$native) {
            return parent::fetchAssociative($query, $params, $types);
        }
        ++$this->nativeAttempts;
        $fault = $this->nativeFault;
        $this->nativeFault = null;
        if ($fault === 'before') {
            throw new \RuntimeException('generic outbox native before');
        }
        $result = parent::fetchAssociative($query, $params, $types);
        $isUpdateReturning = str_contains($query, 'UPDATE ') && str_contains($query, 'RETURNING ');
        if ($this->nativeRowOverride !== null && $isUpdateReturning) {
            $override = $this->nativeRowOverride;
            $this->nativeRowOverride = null;
            ++$this->nativeMalformedOverrideApplications;
            if (is_array($result)) {
                return [...$result, ...$override];
            }
            return $override;
        }
        if ($fault === 'after') {
            throw new \RuntimeException('generic outbox native after');
        }

        return $result;
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->traceSql($query);
        if (str_contains($query, 'audit_outbox_records') && str_contains($query, 'INSERT')) {
            $number = ++$this->appendAttempts;
            $fault = $this->c3Fault;
            $this->c3Fault = null;
            if ($fault === 'before') {
                throw new \RuntimeException('generic outbox c3 before');
            }
            $result = parent::fetchOne($query, $params, $types);
            ++$this->appendWrites;
            if ($fault === 'after' || $this->appendFaultAt === $number) {
                $this->appendFaultAt = null;
                throw new \RuntimeException('generic-outbox-c3-after-ingress');
            }

            return $result;
        }

        return parent::fetchOne($query, $params, $types);
    }

    public function executeStatement(string $query, array $params = [], array $types = []): int|string
    {
        $this->traceSql($query);

        return parent::executeStatement($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->traceSql($query);

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function commit(): void
    {
        ++$this->commits;
        $fault = $this->commitFault;
        $this->commitFault = null;
        if ($fault === 'before') {
            throw new \RuntimeException('generic outbox commit before');
        }
        parent::commit();
        if ($fault === 'after') {
            throw new \RuntimeException('generic-outbox-c3-after-ingress');
        }
    }

    public function rollBack(): void
    {
        ++$this->rollbacks;
        $fault = $this->rollbackFault;
        $this->rollbackFault = null;
        if ($fault === 'before') {
            throw new \RuntimeException('generic outbox rollback before');
        }
        parent::rollBack();
        if ($fault === 'after') {
            throw new \RuntimeException('generic outbox rollback after');
        }
    }

    public function close(): void
    {
        ++$this->closes;
        $fault = $this->closeFault;
        $this->closeFault = false;
        parent::close();
        $this->closed = true;
        if ($fault) {
            throw new \RuntimeException('generic outbox close');
        }
    }

    private function isNativeOutboxQuery(string $query): bool
    {
        return str_contains($query, 'outbox_records') && !str_contains($query, 'audit_outbox_records');
    }

    private function traceSql(string $query): void
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query));
        $this->sqlStatements[] = is_string($normalized) ? $normalized : $query;
    }
}

final class GenericOutboxRelaySender implements OperationSender
{
    /** @param list<string> $failedOperationIds */
    public function __construct(
        private readonly array $failedOperationIds,
    ) {}

    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        if (in_array($message->operationId()->toString(), $this->failedOperationIds, true)) {
            throw new \RuntimeException('generic outbox sender failure');
        }

        return new DeferredAcknowledgement(
            $message->operationId(),
            new DateTimeImmutable('2026-10-02T12:00:00.654321Z'),
        );
    }
}
