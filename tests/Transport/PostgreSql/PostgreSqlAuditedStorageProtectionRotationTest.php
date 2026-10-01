<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
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
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditWriter;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;
use Traversable;

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-empty-catch-clause
 */
final class PostgreSqlAuditedStorageProtectionRotationTest extends TestCase
{
    private const string SCHEMA = 'blackops_rotation_audit';
    private const string OPERATION = '019fca20-0000-7000-8000-000000000001';
    private const string RECORD = '019fca20-0000-7000-8000-000000000002';
    private const string OPERATION_TYPE = 'rotation.fixture';
    private const string BUILD_ID = 'rotation-audit-build';
    private const string OLD_KEY = 'old:v1';
    private const string NEW_KEY = 'new:v1';
    private const string TIME = '2026-09-29T12:00:00.000000Z';

    private Connection $admin;
    private Connection $connection;
    private Connection $probe;
    private PostgreSqlAuditSchema $recordSchema;
    private PostgreSqlAuditOutboxSchema $outboxSchema;
    private AuditProtectedBodyCodec $protection;
    private int $uuidBatch = 1;

    protected function setUp(): void
    {
        $this->admin = $this->connection();
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        new DatabaseMigrationRunner($this->admin, self::SCHEMA)->migrate();
        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $this->recordSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        $this->outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema(self::SCHEMA));
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

    public function testAllNinePurposeIdentitiesPersistNativeAndProtectedC3Events(): void
    {
        $this->insertPurposeFixtures();
        $rotation = $this->rotation($this->connection);
        $before = [];

        foreach (StoragePurpose::cases() as $purpose) {
            $scope = $this->scope($purpose, 'all-' . $purpose->value, batchSize: 2);
            $before[$purpose->value] = $this->purposeEnvelope($purpose);
            $plan = $rotation->plan($scope);
            self::assertSame($purpose->value, $plan->purpose);
            self::assertSame(1, $plan->selected);
            self::assertSame(0, $plan->rotated);
            self::assertSame($before[$purpose->value], $this->purposeEnvelope($purpose));

            $result = $rotation->rotate($scope);
            self::assertSame(1, $result->selected);
            self::assertSame(1, $result->rotated);
            self::assertSame(0, $result->failed);
            self::assertSame('complete', $result->state);
            self::assertSame(self::NEW_KEY, $this->purposeKey($purpose));
            self::assertNotSame($before[$purpose->value], $this->purposeEnvelope($purpose));
            self::assertNotSame('', $this->purposePlaintext($purpose));
        }

        $events = $this->logicalOutboxRows();
        self::assertCount(27, $events);
        foreach ($events as $event) {
            $logical = $event['logicalRecord'];
            $eventType = (string) $logical['event']['type'];
            [$phase, $status, $code] = match ($eventType) {
                'rotation.storage.started' => ['started', 'unknown', 'rotation_started'],
                'rotation.storage.progressed' => ['committed', 'unknown', 'rotation_progressed'],
                'rotation.storage.succeeded' => ['succeeded', 'succeeded', 'rotation_succeeded'],
                'rotation.storage.failed' => ['failed', 'failed', 'rotation_failed'],
                default => self::fail('Unexpected persisted rotation event.'),
            };
            self::assertSame(
                [
                    'type' => $eventType,
                    'version' => 1,
                    'category' => 'rotation',
                    'catalogHash' => FrameworkOperationalAuditEventCatalog::catalog()->catalogHash,
                ],
                $logical['event'],
            );
            self::assertSame(
                ['id' => $logical['action']['id'], 'type' => $eventType, 'phase' => $phase],
                $logical['action'],
            );
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/D', (string) $logical['action']['id']);
            self::assertSame(['type' => 'protected_storage', 'id' => $logical['resource']['id']], $logical['resource']);
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/D', (string) $logical['resource']['id']);
            self::assertSame(['status' => $status, 'code' => $code], $logical['outcome']);
            self::assertSame(
                [
                    'id' => 'blackops.rotation.storage',
                    'version' => 1,
                    'decision' => 'not_applicable',
                    'reasonCode' => $code,
                    'auditDelivery' => 'durable',
                ],
                $logical['policy'],
            );
            self::assertSame(['occurredAt' => self::TIME, 'recordedAt' => self::TIME], $logical['time']);
            self::assertSame(
                [
                    'owner' => 'framework',
                    'component' => 'blackops.rotation.storage',
                    'runtime' => 'worker',
                    'buildId' => self::BUILD_ID,
                ],
                $logical['source'],
            );
            self::assertSame(
                [
                    'principal' => ['type' => 'operator', 'id' => 'rotation-actor'],
                    'execution' => ['type' => 'storage', 'id' => 'rotation-execution'],
                ],
                $logical['actor'],
            );
            self::assertSame(['correlationId' => 'rotation-correlation'], $logical['correlation']);
            self::assertSame(['version' => 1], $logical['evidence']);
            $encoded = json_encode($logical, JSON_THROW_ON_ERROR);
            foreach ([self::OLD_KEY, self::NEW_KEY, 'native-actor', 'native-reason'] as $secret) {
                self::assertStringNotContainsString($secret, $encoded);
            }
        }
        $types = array_map(static fn(array $row): string => (string) $row['logicalRecord']['event']['type'], $events);
        self::assertSame(
            9,
            count(array_filter($types, static fn(string $type): bool => $type === 'rotation.storage.started')),
        );
        self::assertSame(
            9,
            count(array_filter($types, static fn(string $type): bool => $type === 'rotation.storage.progressed')),
        );
        self::assertSame(
            9,
            count(array_filter($types, static fn(string $type): bool => $type === 'rotation.storage.succeeded')),
        );

        foreach (StoragePurpose::cases() as $purpose) {
            $scope = $this->scope($purpose, 'all-' . $purpose->value, batchSize: 2);
            $audit = $this->probe->fetchAssociative('SELECT audit_id, scope_hash, state, selected_count, rotated_count, skipped_count, failed_count, finished_at FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$scope->checkpoint]);
            self::assertIsArray($audit);
            self::assertSame($scope->scopeHash(), (string) $audit['scope_hash']);
            self::assertSame('complete', $audit['state']);
            self::assertSame('1', (string) $audit['selected_count']);
            self::assertSame('1', (string) $audit['rotated_count']);
            self::assertSame('0', (string) $audit['skipped_count']);
            self::assertSame('0', (string) $audit['failed_count']);
            self::assertNotNull($audit['finished_at']);

            $cursor = $this->purposeCursor($purpose);
            $eventsForScope = $this->eventsForCheckpoint($scope->scopeHash(), $cursor);
            self::assertCount(3, $eventsForScope);
            foreach ($eventsForScope as $event) {
                $eventType = (string) $event['logicalRecord']['event']['type'];
                $this->assertPersistedEventIdentity(
                    $event,
                    (string) $audit['audit_id'],
                    $scope->scopeHash(),
                    $eventType === 'rotation.storage.progressed' ? $cursor : null,
                    null,
                );
            }
        }
    }

    public function testScopedAllAndGlobalSubjectsKeepPersistedTenantBindingAndCasSkipTruth(): void
    {
        $tenantA = new TenantRef('org', 'tenant-a');
        $tenantB = new TenantRef('org', 'tenant-b');
        $recordA = '019fca20-0000-7000-8000-000000000010';
        $recordB = '019fca20-0000-7000-8000-000000000011';
        $globalRecord = '019fca20-0000-7000-8000-000000000012';
        $this->insertJournalFixture($recordA, $tenantA, 'tenant-a');
        $this->insertJournalFixture($recordB, $tenantB, 'tenant-b');
        $this->insertJournalFixture($globalRecord, null, 'global');
        $rotation = $this->rotation($this->connection);

        $scopedScope = $this->scope(StoragePurpose::JournalRecord, 'scoped-a', $tenantA, 10);
        $scoped = $rotation->rotate($scopedScope);
        self::assertSame(1, $scoped->rotated);
        $scopedAudit = $this->probe->fetchAssociative('SELECT audit_id FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$scopedScope->checkpoint]);
        self::assertIsArray($scopedAudit);
        $scopedEvents = $this->eventsForCheckpoint($scopedScope->scopeHash(), $recordA);
        self::assertCount(3, $scopedEvents);
        foreach ($scopedEvents as $event) {
            $eventType = (string) $event['logicalRecord']['event']['type'];
            $this->assertPersistedEventIdentity(
                $event,
                (string) $scopedAudit['audit_id'],
                $scopedScope->scopeHash(),
                $eventType === 'rotation.storage.progressed' ? $recordA : null,
                $tenantA,
            );
        }
        self::assertSame(
            self::NEW_KEY,
            new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId(PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE tenant_type = ? AND tenant_id = ?', ['org', 'tenant-a']))),
        );

        $allScope = $this->scope(StoragePurpose::JournalRecord, 'all-tenants', null, 10);
        $all = $rotation->rotate($allScope);
        self::assertSame(2, $all->rotated);
        $allAudit = $this->probe->fetchAssociative('SELECT audit_id FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$allScope->checkpoint]);
        self::assertIsArray($allAudit);
        $allEvents = $this->eventsForCheckpoint($allScope->scopeHash());
        foreach ([$recordB, $globalRecord] as $cursor) {
            foreach ($this->eventsForCheckpoint($allScope->scopeHash(), $cursor) as $event) {
                if ($event['logicalRecord']['event']['type'] === 'rotation.storage.progressed') {
                    $allEvents[] = $event;
                }
            }
        }
        usort(
            $allEvents,
            static fn(array $left, array $right): int => (
                (int) $left['ingressOrdinal'] <=> (int) $right['ingressOrdinal']
            ),
        );
        self::assertCount(4, $allEvents);
        $allProgressIndex = 0;
        $allProgressCursors = [$recordB, $globalRecord];
        $allProgressTenants = [$tenantB, null];
        foreach ($allEvents as $event) {
            $eventType = (string) $event['logicalRecord']['event']['type'];
            $isProgress = $eventType === 'rotation.storage.progressed';
            $this->assertPersistedEventIdentity(
                $event,
                (string) $allAudit['audit_id'],
                $allScope->scopeHash(),
                $isProgress ? $allProgressCursors[$allProgressIndex] : null,
                $isProgress ? $allProgressTenants[$allProgressIndex++] : null,
            );
        }
        self::assertSame(2, $allProgressIndex);
        $scopeEvents = array_values(array_filter($this->logicalOutboxRows(), static fn(array $row): bool => in_array(
            $row['logicalRecord']['event']['type'],
            ['rotation.storage.started', 'rotation.storage.succeeded'],
            true,
        )));
        self::assertContains(null, array_map(
            static fn(array $row): mixed => $row['logicalRecord']['tenant'],
            $scopeEvents,
        ));
        $progress = array_values(array_filter(
            $this->logicalOutboxRows(),
            static fn(array $row): bool => $row['logicalRecord']['event']['type'] === 'rotation.storage.progressed',
        ));
        self::assertCount(3, $progress);
        $bindings = array_map(static fn(array $row): mixed => $row['logicalRecord']['tenant'], $progress);
        self::assertContains(['type' => 'org', 'id' => 'tenant-a'], $bindings);
        self::assertContains(['type' => 'org', 'id' => 'tenant-b'], $bindings);
        self::assertContains(null, $bindings);

        $raceRecord = '019fca20-0000-7000-8000-000000000013';
        $this->insertJournalFixture($raceRecord, $tenantB, 'race');
        $replacement = $this->oldEnvelope(StoragePurpose::JournalRecord, $raceRecord, $tenantB, 'replacement');
        $race = new RotationCasRaceConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            $this->probe,
            '"' . self::SCHEMA . '"."journal"',
            $raceRecord,
            $replacement,
            $tenantA,
        );
        $rotation = $this->rotation($race);
        $casScope = $this->scope(StoragePurpose::JournalRecord, 'cas-skip', null, 10);
        $result = $rotation->rotate($casScope);
        self::assertSame(1, $result->selected);
        self::assertSame(1, $result->skipped);
        self::assertSame(0, $result->rotated);
        $casAudit = $this->probe->fetchAssociative('SELECT audit_id FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$casScope->checkpoint]);
        self::assertIsArray($casAudit);
        $casEvents = $this->eventsForCheckpoint($casScope->scopeHash(), $raceRecord);
        self::assertCount(3, $casEvents);
        foreach ($casEvents as $event) {
            $eventType = (string) $event['logicalRecord']['event']['type'];
            $this->assertPersistedEventIdentity(
                $event,
                (string) $casAudit['audit_id'],
                $casScope->scopeHash(),
                $eventType === 'rotation.storage.progressed' ? $raceRecord : null,
                $eventType === 'rotation.storage.progressed' ? $tenantB : null,
            );
        }
        $progressAfterRace = array_values(array_filter(
            $this->logicalOutboxRows(),
            static fn(array $row): bool => $row['logicalRecord']['event']['type'] === 'rotation.storage.progressed',
        ));
        self::assertSame(
            ['type' => 'org', 'id' => 'tenant-b'],
            $progressAfterRace[array_key_last($progressAfterRace)]['logicalRecord']['tenant'],
        );
        self::assertSame(
            ['org', 'tenant-a'],
            array_values(
                $this->probe->fetchAssociative('SELECT tenant_type, tenant_id FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$raceRecord]) ?: [],
            ),
        );
        self::assertSame(
            $replacement,
            PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$raceRecord])),
        );
        $this->assertConnectionClosed($race);
    }

    public function testCasReplacementWithNewKeySkipsAndAdvancesCursorWithHistoricalTenantTruth(): void
    {
        $tenant = new TenantRef('org', 'tenant-key-mismatch');
        $record = '019fca20-0000-7000-8000-000000000014';
        $this->insertJournalFixture($record, $tenant, 'key-mismatch');
        $replacement = $this->newEnvelope(StoragePurpose::JournalRecord, $record, $tenant, 'replacement-new-key');
        $race = new RotationCasRaceConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            $this->probe,
            '"' . self::SCHEMA . '"."journal"',
            $record,
            $replacement,
            $tenant,
        );
        $scope = $this->scope(StoragePurpose::JournalRecord, 'key-mismatch-skip', null, 10);

        try {
            $result = $this->rotation($race)->rotate($scope);
            self::assertSame(1, $result->selected);
            self::assertSame(1, $result->skipped);
            self::assertSame(0, $result->rotated);
            self::assertSame(
                ['rotation.storage.started', 'rotation.storage.progressed', 'rotation.storage.succeeded'],
                $this->eventTypesForCheckpoint($scope->scopeHash(), $record),
            );
            $progress = array_values(array_filter(
                $this->eventsForCheckpoint($scope->scopeHash(), $record),
                static fn(array $row): bool => $row['logicalRecord']['event']['type'] === 'rotation.storage.progressed',
            ));
            self::assertCount(1, $progress);
            self::assertSame(['type' => 'org', 'id' => 'tenant-key-mismatch'], $progress[0]['logicalRecord']['tenant']);
            self::assertSame(
                $replacement,
                PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record])),
            );
            self::assertSame(
                self::NEW_KEY,
                new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($replacement),
            );
        } finally {
            $this->assertConnectionClosed($race);
        }
    }

    public function testSelectedNewKeySnapshotIsSkippedWithoutNativeMutation(): void
    {
        $tenant = new TenantRef('org', 'tenant-selected-new-key');
        $record = '019fca20-0000-7000-8000-000000000015';
        $this->insertJournalFixture($record, $tenant, 'selected-new-key');
        $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
        . self::SCHEMA
        . '.journal WHERE record_id = ?', [$record]));
        $selectedSnapshot = $this->newEnvelope(
            StoragePurpose::JournalRecord,
            $record,
            $tenant,
            'selected-new-key-snapshot',
        );
        $selected = new RotationSelectedKeyMismatchConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            'journal',
            $selectedSnapshot,
        );
        $scope = $this->scope(StoragePurpose::JournalRecord, 'selected-new-key', null, 10);

        try {
            $result = $this->rotation($selected)->rotate($scope);
            self::assertTrue($selected->injected());
            self::assertSame(0, $result->selected);
            self::assertSame(1, $result->skipped);
            self::assertSame(0, $result->rotated);
            self::assertSame('complete', $result->state);
            self::assertSame(
                ['rotation.storage.started', 'rotation.storage.progressed', 'rotation.storage.succeeded'],
                $this->eventTypesForCheckpoint($scope->scopeHash(), $record),
            );
            self::assertSame(
                $before,
                PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record])),
            );
            $progress = array_values(array_filter(
                $this->eventsForCheckpoint($scope->scopeHash(), $record),
                static fn(array $row): bool => $row['logicalRecord']['event']['type'] === 'rotation.storage.progressed',
            ));
            self::assertCount(1, $progress);
            self::assertSame(
                ['type' => 'org', 'id' => 'tenant-selected-new-key'],
                $progress[0]['logicalRecord']['tenant'],
            );
        } finally {
            $this->assertConnectionClosed($selected);
        }
    }

    public function testMalformedTenantIsRejectedBeforeDecodeAndDoesNotAdvanceNativeRow(): void
    {
        $tenant = new TenantRef('org', 'tenant-malformed');
        $this->insertJournalFixture('019fca20-0000-7000-8000-000000000020', $tenant, 'malformed');
        $fault = new RotationSubjectFaultConnection($this->connection->getParams(), $this->connection->getDriver());
        $rotation = $this->rotation($fault);
        $fault->arm('tenant');

        $this->expectException(AuditDeliveryException::class);
        try {
            $rotation->rotate($this->scope(StoragePurpose::JournalRecord, 'malformed-tenant', $tenant));
        } finally {
            self::assertSame(
                1,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ? AND encoded_record IS NOT NULL', [
                    '019fca20-0000-7000-8000-000000000020',
                ]),
            );
            self::assertSame(1, $this->countEvents('rotation.storage.started'));
            self::assertSame(0, $this->countEvents('rotation.storage.progressed'));
            self::assertSame(0, $this->countEvents('rotation.storage.succeeded'));
            self::assertSame(0, $this->countEvents('rotation.storage.failed'));
            $this->assertConnectionClosed($fault);
        }
    }

    public function testMalformedCursorIdentityIsRejectedBeforeDecode(): void
    {
        $this->insertJournalFixture('019fca20-0000-7000-8000-000000000021', null, 'malformed-cursor');
        $fault = new RotationSubjectFaultConnection($this->connection->getParams(), $this->connection->getDriver());
        $rotation = $this->rotation($fault);
        $fault->arm('cursor');

        $this->expectException(AuditDeliveryException::class);
        try {
            $rotation->rotate($this->scope(StoragePurpose::JournalRecord, 'malformed-cursor'));
        } finally {
            self::assertSame(1, $this->countEvents('rotation.storage.started'));
            self::assertSame(0, $this->countEvents('rotation.storage.progressed'));
            self::assertSame(0, $this->countEvents('rotation.storage.failed'));
            $this->assertConnectionClosed($fault);
        }
    }

    public function testMalformedTenantFormsAreRejectedBeforeDecode(): void
    {
        $cases = [
            'tenant-missing',
            'tenant-wrong-type',
            'tenant-whitespace',
            'tenant-control',
            'tenant-length',
            'tenant-mismatch',
        ];
        if (class_exists('Normalizer')) {
            $cases[] = 'tenant-nfc';
        }
        foreach ($cases as $index => $mode) {
            $record = sprintf('019fca20-0000-7000-8000-%012x', 40 + $index);
            $checkpoint = 'malformed-' . $mode;
            $tenant = new TenantRef('org', 'tenant-form-' . $mode);
            $this->insertJournalFixture($record, $tenant, 'malformed-' . $mode);
            $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            $fault = new RotationSubjectFaultConnection($this->connection->getParams(), $this->connection->getDriver());
            $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, $tenant);
            $fault->arm($mode);
            try {
                $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
                self::assertInstanceOf(AuditDeliveryException::class, $failure);
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertSame(['rotation.storage.started'], $this->eventTypesForCheckpoint($scope->scopeHash()));
                self::assertSame(
                    $before,
                    PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                    . self::SCHEMA
                    . '.journal WHERE record_id = ?', [$record])),
                );
            } finally {
                $this->assertConnectionClosed($fault);
            }
        }
    }

    public function testMalformedCompositeCursorIsRejectedBeforeDecode(): void
    {
        $this->insertPurposeFixtures();
        $fault = new RotationSubjectFaultConnection($this->connection->getParams(), $this->connection->getDriver());
        $scope = $this->scope(StoragePurpose::IdempotencyResponse, 'malformed-composite');
        $fault->arm('composite-overflow');

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertInstanceOf(AuditDeliveryException::class, $failure);
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertSame(['rotation.storage.started'], $this->eventTypesForCheckpoint($scope->scopeHash()));
        } finally {
            $this->assertConnectionClosed($fault);
        }
    }

    public function testAdmissionRunningCompleteZeroRowAndWrongConnectionHaveNoEarlyEffects(): void
    {
        $this->insertJournalFixture('019fca20-0000-7000-8000-000000000030', null, 'running');
        $rotation = $this->rotation($this->connection);
        $scope = $this->scope(StoragePurpose::JournalRecord, 'running-truth', null, 1);
        $running = $rotation->rotate($scope);
        self::assertSame('running', $running->state);
        self::assertSame(0, $this->countEvents('rotation.storage.succeeded'));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = ? WHERE record_id = ?',
            [
                $this->oldEnvelope(
                    StoragePurpose::JournalRecord,
                    '019fca20-0000-7000-8000-000000000030',
                    null,
                    'running',
                ),
                '019fca20-0000-7000-8000-000000000030',
            ],
            [ParameterType::BINARY, ParameterType::STRING],
        );
        $complete = $rotation->rotate($scope);
        self::assertSame('complete', $complete->state);
        self::assertSame(1, $this->countEvents('rotation.storage.succeeded'));
        self::assertSame(
            self::OLD_KEY,
            new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::OLD_KEY))->keyId(PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', ['019fca20-0000-7000-8000-000000000030']))),
        );

        $zero = $rotation->rotate($this->scope(StoragePurpose::DeadLetterReason, 'zero-row'));
        self::assertSame('complete', $zero->state);
        self::assertSame(2, $this->countEvents('rotation.storage.succeeded'));

        $this->connection->beginTransaction();
        $ambientPlanRejected = false;
        try {
            $rotation->plan($this->scope(StoragePurpose::JournalRecord, 'ambient-plan'));
        } catch (AuditDeliveryException $failure) {
            $ambientPlanRejected = true;
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['ambient-plan']),
            );
        }
        self::assertTrue($ambientPlanRejected);
        try {
            $rotation->rotate($this->scope(StoragePurpose::JournalRecord, 'ambient'));
            self::fail('An ambient transaction must be rejected before native work.');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['ambient']),
            );
        } finally {
            $this->restoreConnection($this->connection);
        }
        $eventsBeforeWrong = $this->countEvents();
        $wrongHook = $this->hook($this->probe);
        $wrong = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY)),
            self::SCHEMA,
            $wrongHook,
        );
        $wrongPlanRejected = false;
        try {
            $wrong->plan($this->scope(StoragePurpose::JournalRecord, 'wrong-connection-plan'));
        } catch (AuditDeliveryException $failure) {
            $wrongPlanRejected = true;
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame($eventsBeforeWrong, $this->countEvents());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['wrong-connection-plan']),
            );
        }
        self::assertTrue($wrongPlanRejected);
        $wrongRejected = false;
        try {
            $wrong->rotate($this->scope(StoragePurpose::JournalRecord, 'wrong-connection'));
        } catch (AuditDeliveryException $failure) {
            $wrongRejected = true;
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame($eventsBeforeWrong, $this->countEvents());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['wrong-connection']),
            );
        }
        self::assertTrue($wrongRejected);

        $this->connection->setAutoCommit(false);
        $nonAutoPlanRejected = false;
        try {
            $rotation->plan($this->scope(StoragePurpose::JournalRecord, 'non-autocommit-plan'));
        } catch (AuditDeliveryException $failure) {
            $nonAutoPlanRejected = true;
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame($eventsBeforeWrong, $this->countEvents());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['non-autocommit-plan']),
            );
        }
        self::assertTrue($nonAutoPlanRejected);
        $nonAutoRotateRejected = false;
        try {
            $rotation->rotate($this->scope(StoragePurpose::JournalRecord, 'non-autocommit'));
        } catch (AuditDeliveryException $failure) {
            $nonAutoRotateRejected = true;
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertSame($eventsBeforeWrong, $this->countEvents());
            self::assertSame(
                0,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', ['non-autocommit']),
            );
        } finally {
            $this->restoreConnection($this->connection);
        }
        self::assertTrue($nonAutoRotateRejected);
    }

    public function testConfiguredPlanPerformsNoWriterClockOrDeliveryEffect(): void
    {
        $record = '019fca20-0000-7000-8000-000000000031';
        $this->insertJournalFixture($record, null, 'plan-no-effects');
        $scope = $this->scope(StoragePurpose::JournalRecord, 'plan-no-effects', null, 2);
        $result = $this->rotation($this->connection, new RotationTraceClock(1, 'plan-writer-clock-must-not-run'))->plan(
            $scope,
        );

        self::assertSame(1, $result->selected);
        self::assertSame(0, $result->rotated);
        self::assertSame(0, $this->countEvents());
        self::assertSame(
            0,
            (int) $this->probe->fetchOne(
                'SELECT count(*) FROM ' . self::SCHEMA . '.storage_protection_rotation_checkpoints',
            ),
        );
        self::assertSame(
            0,
            (int) $this->probe->fetchOne(
                'SELECT count(*) FROM ' . self::SCHEMA . '.storage_protection_rotation_audits',
            ),
        );
    }

    public function testRejectedAdmissionDoesNoNativeSqlAdvisoryClockOrEventWork(): void
    {
        $record = '019fca20-0000-7000-8000-000000000032';
        $this->insertJournalFixture($record, null, 'admission-work');
        $codec = new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY));

        $wrongConnection = new RotationWorkCountingConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
        );
        $wrongClock = new RotationTraceClock(1, 'wrong-admission-clock');
        $wrong = new PostgreSqlStorageProtectionRotation(
            $wrongConnection,
            $codec,
            self::SCHEMA,
            $this->hook($this->probe, $wrongClock),
        );
        try {
            foreach (['wrong-plan' => false, 'wrong-rotate' => true] as $checkpoint => $confirmed) {
                $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
                $failure = $this->captureFailure(fn() => $confirmed ? $wrong->rotate($scope) : $wrong->plan($scope));
                self::assertInstanceOf(AuditDeliveryException::class, $failure);
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            }
            self::assertSame(0, $wrongConnection->sqlCalls());
            self::assertSame(0, $wrongConnection->advisoryCalls());
            self::assertSame(0, $wrongConnection->transactionCalls());
            self::assertSame(0, $wrongClock->calls());
        } finally {
            $this->assertConnectionClosed($wrongConnection);
        }

        $nonAutoConnection = new RotationWorkCountingConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
        );
        $nonAutoConnection->setAutoCommit(false);
        $nonAutoConnection->resetWorkCounters();
        $nonAutoClock = new RotationTraceClock(1, 'non-auto-admission-clock');
        $nonAuto = new PostgreSqlStorageProtectionRotation(
            $nonAutoConnection,
            $codec,
            self::SCHEMA,
            $this->hook($nonAutoConnection, $nonAutoClock),
        );
        try {
            foreach (['non-auto-plan' => false, 'non-auto-rotate' => true] as $checkpoint => $confirmed) {
                $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
                $failure = $this->captureFailure(fn() => $confirmed
                    ? $nonAuto->rotate($scope)
                    : $nonAuto->plan($scope));
                self::assertInstanceOf(AuditDeliveryException::class, $failure);
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            }
            self::assertSame(0, $nonAutoConnection->sqlCalls());
            self::assertSame(0, $nonAutoConnection->advisoryCalls());
            self::assertSame(0, $nonAutoConnection->transactionCalls());
            self::assertSame(0, $nonAutoClock->calls());
        } finally {
            $this->assertConnectionClosed($nonAutoConnection);
        }

        $ambientConnection = new RotationWorkCountingConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
        );
        $ambientConnection->beginTransaction();
        $ambientConnection->resetWorkCounters();
        $ambientClock = new RotationTraceClock(1, 'ambient-admission-clock');
        $ambient = new PostgreSqlStorageProtectionRotation(
            $ambientConnection,
            $codec,
            self::SCHEMA,
            $this->hook($ambientConnection, $ambientClock),
        );
        try {
            foreach (['ambient-plan' => false, 'ambient-rotate' => true] as $checkpoint => $confirmed) {
                $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
                $failure = $this->captureFailure(fn() => $confirmed
                    ? $ambient->rotate($scope)
                    : $ambient->plan($scope));
                self::assertInstanceOf(AuditDeliveryException::class, $failure);
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            }
            self::assertSame(0, $ambientConnection->sqlCalls());
            self::assertSame(0, $ambientConnection->advisoryCalls());
            self::assertSame(0, $ambientConnection->transactionCalls());
            self::assertSame(0, $ambientClock->calls());
        } finally {
            $this->assertConnectionClosed($ambientConnection);
        }

        self::assertSame(0, $this->countEvents());
        self::assertSame(
            0,
            (int) $this->probe->fetchOne(
                'SELECT count(*) FROM ' . self::SCHEMA . '.storage_protection_rotation_checkpoints',
            ),
        );
    }

    public function testNativeFailureAndBoundedInterruptionRecoveryEmitOnlyTruthfulEvents(): void
    {
        $tenant = new TenantRef('org', 'tenant-failure');
        $this->insertJournalFixture('019fca20-0000-7000-8000-000000000040', $tenant, 'failure');
        $this->corruptJournal('019fca20-0000-7000-8000-000000000040');
        $failedScope = $this->scope(StoragePurpose::JournalRecord, 'native-failure', $tenant);
        $failed = $this->rotation($this->connection)->rotate($failedScope);
        self::assertSame(1, $failed->failed);
        self::assertSame('failed', $failed->state);
        self::assertSame(1, $this->countEvents('rotation.storage.started'));
        self::assertSame(1, $this->countEvents('rotation.storage.failed'));
        self::assertSame(0, $this->countEvents('rotation.storage.progressed'));
        $failedAudit = $this->probe->fetchAssociative(
            'SELECT audit_id, state, selected_count, rotated_count, skipped_count, failed_count, failure_fingerprint, finished_at FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?',
            [$failedScope->checkpoint],
        );
        self::assertIsArray($failedAudit);
        $failedFingerprint = 'v1:' . hash('sha256', $failedScope->scopeHash() . ':1');
        self::assertSame('failed', $failedAudit['state']);
        self::assertSame('1', (string) $failedAudit['selected_count']);
        self::assertSame('0', (string) $failedAudit['rotated_count']);
        self::assertSame('0', (string) $failedAudit['skipped_count']);
        self::assertSame('1', (string) $failedAudit['failed_count']);
        self::assertSame($failedFingerprint, $failedAudit['failure_fingerprint']);
        self::assertNotNull($failedAudit['finished_at']);
        $failedCheckpoint = $this->probe->fetchAssociative('SELECT state, cursor_value, failure_fingerprint, updated_at FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$failedScope->checkpoint]);
        self::assertIsArray($failedCheckpoint);
        self::assertSame('failed', $failedCheckpoint['state']);
        self::assertNull($failedCheckpoint['cursor_value']);
        self::assertSame($failedFingerprint, $failedCheckpoint['failure_fingerprint']);
        self::assertNotNull($failedCheckpoint['updated_at']);
        foreach ($this->eventsForCheckpoint($failedScope->scopeHash()) as $event) {
            $eventType = (string) $event['logicalRecord']['event']['type'];
            $this->assertPersistedEventIdentity(
                $event,
                (string) $failedAudit['audit_id'],
                $failedScope->scopeHash(),
                null,
                $tenant,
            );
            $this->assertRotationLogicalTuple($event['logicalRecord'], $eventType);
        }

        foreach ([0, 1, 3, 100] as $number) {
            $checkpoint = 'interrupt-' . $number;
            $scope = $this->scope(StoragePurpose::DeadLetterReason, $checkpoint);
            $this->insertInterruptedAudits($scope, $number);
            $oldAuditIds = array_map(
                static fn(mixed $auditId): string => (string) $auditId,
                $this->probe->fetchFirstColumn('SELECT audit_id FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\' ORDER BY audit_id', [
                    $checkpoint,
                ]),
            );
            $result = $this->rotation($this->connection)->rotate($scope);
            self::assertSame('complete', $result->state);
            self::assertSame(
                $number,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'failed\'', [$checkpoint]),
            );
            self::assertSame($number + 2, $this->countEventsForCheckpoint($scope->scopeHash()));
            $currentAudit = $this->probe->fetchAssociative('SELECT audit_id FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'complete\'', [$checkpoint]);
            self::assertIsArray($currentAudit);
            $failedIndex = 0;
            foreach ($this->eventsForCheckpoint($scope->scopeHash()) as $event) {
                $eventType = (string) $event['logicalRecord']['event']['type'];
                $auditId = $eventType === 'rotation.storage.failed'
                    ? $oldAuditIds[$failedIndex++]
                    : (string) $currentAudit['audit_id'];
                $this->assertPersistedEventIdentity($event, $auditId, $scope->scopeHash(), null, null);
                $this->assertRotationLogicalTuple($event['logicalRecord'], $eventType);
            }
            self::assertSame($number, $failedIndex);
            $native = $this->nativeRotationSnapshot($checkpoint);
            self::assertIsArray($native['checkpoint']);
            self::assertSame($checkpoint, $native['checkpoint']['checkpoint_id']);
            self::assertSame('complete', $native['checkpoint']['state']);
            self::assertNull($native['checkpoint']['cursor_value']);
            self::assertNull($native['checkpoint']['failure_fingerprint']);
            $auditsById = [];
            foreach ($native['audits'] as $auditRow) {
                $auditsById[(string) $auditRow['audit_id']] = $auditRow;
            }
            foreach ($oldAuditIds as $oldAuditId) {
                self::assertArrayHasKey($oldAuditId, $auditsById);
                $oldAudit = $auditsById[$oldAuditId];
                self::assertSame('failed', $oldAudit['state']);
                self::assertSame('0', (string) $oldAudit['selected_count']);
                self::assertSame('0', (string) $oldAudit['rotated_count']);
                self::assertSame('0', (string) $oldAudit['skipped_count']);
                self::assertSame('1', (string) $oldAudit['failed_count']);
                self::assertSame(
                    'v1:' . hash('sha256', $scope->scopeHash() . ':interrupted'),
                    $oldAudit['failure_fingerprint'],
                );
                self::assertNotNull($oldAudit['finished_at']);
            }
            self::assertArrayHasKey((string) $currentAudit['audit_id'], $auditsById);
            $currentAuditRow = $auditsById[(string) $currentAudit['audit_id']];
            self::assertSame('complete', $currentAuditRow['state']);
            self::assertSame('0', (string) $currentAuditRow['selected_count']);
            self::assertSame('0', (string) $currentAuditRow['rotated_count']);
            self::assertSame('0', (string) $currentAuditRow['skipped_count']);
            self::assertSame('0', (string) $currentAuditRow['failed_count']);
            self::assertNull($currentAuditRow['failure_fingerprint']);
            self::assertNotNull($currentAuditRow['finished_at']);
        }

        $sentinelScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-sentinel');
        $this->insertInterruptedAudits($sentinelScope, 1);
        $sentinelAuditId = str_repeat('f', 32);
        $sentinelScopeHash = hash('sha256', 'interrupt-sentinel-other-scope');
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.storage_protection_rotation_audits (audit_id, checkpoint_id, scope_hash, purpose, old_key_id, new_key_id, actor, reason, state, started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'started\', ?)',
            [
                $sentinelAuditId,
                $sentinelScope->checkpoint,
                $sentinelScopeHash,
                $sentinelScope->purpose->value,
                self::OLD_KEY,
                self::NEW_KEY,
                'fixture-sentinel',
                'fixture-sentinel',
                '2026-09-29 00:00:00+00',
            ],
        );
        $sentinelBefore = $this->probe->fetchAssociative(
            'SELECT audit_id, checkpoint_id, scope_hash, state, selected_count, rotated_count, skipped_count, failed_count, failure_fingerprint, started_at, finished_at FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE audit_id = ?',
            [$sentinelAuditId],
        );
        self::assertIsArray($sentinelBefore);
        $sentinelResult = $this->rotation($this->connection)->rotate($sentinelScope);
        self::assertSame('complete', $sentinelResult->state);
        self::assertSame(3, $this->countEventsForCheckpoint($sentinelScope->scopeHash()));
        $sentinelAfter = $this->probe->fetchAssociative(
            'SELECT audit_id, checkpoint_id, scope_hash, state, selected_count, rotated_count, skipped_count, failed_count, failure_fingerprint, started_at, finished_at FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE audit_id = ?',
            [$sentinelAuditId],
        );
        self::assertSame($sentinelBefore, $sentinelAfter);

        $overflowScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-overflow');
        $this->insertInterruptedAudits($overflowScope, 101);
        $overflowBefore = $this->nativeRotationSnapshot($overflowScope->checkpoint);
        $overflowClock = new RotationTraceClock(1, 'overflow-writer-clock-must-not-run');
        $overflowFailure = $this->captureFailure(
            fn() => $this->rotation($this->connection, $overflowClock)->rotate($overflowScope),
        );
        self::assertInstanceOf(\RuntimeException::class, $overflowFailure);
        self::assertSame('Rotation interruption recovery is too large.', $overflowFailure->getMessage());
        self::assertSame(0, $overflowFailure->getCode());
        self::assertNull($overflowFailure->getPrevious());
        self::assertSame(0, $overflowClock->calls());
        self::assertSame($overflowBefore, $this->nativeRotationSnapshot($overflowScope->checkpoint));
        self::assertSame(
            101,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\'', [
                $overflowScope->checkpoint,
            ]),
        );
        self::assertSame(0, $this->countEventsForCheckpoint($overflowScope->scopeHash()));

        $invalidScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-invalid');
        $this->insertInterruptedAudits($invalidScope, 2, invalidId: true, invalidIndex: 1);
        $invalidIds = array_map(
            static fn(mixed $auditId): string => (string) $auditId,
            $this->probe->fetchFirstColumn('SELECT audit_id FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? ORDER BY audit_id', [
                $invalidScope->checkpoint,
            ]),
        );
        self::assertCount(2, $invalidIds);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/D', $invalidIds[0]);
        self::assertSame('not-a-valid-audit-id', $invalidIds[1]);
        $invalidBefore = $this->nativeRotationSnapshot($invalidScope->checkpoint);
        $invalidClock = new RotationTraceClock(1, 'invalid-writer-clock-must-not-run');
        $invalidFailure = $this->captureFailure(
            fn() => $this->rotation($this->connection, $invalidClock)->rotate($invalidScope),
        );
        self::assertInstanceOf(\RuntimeException::class, $invalidFailure);
        self::assertSame('Rotation interruption audit identifier is invalid.', $invalidFailure->getMessage());
        self::assertSame(0, $invalidFailure->getCode());
        self::assertNull($invalidFailure->getPrevious());
        self::assertSame(0, $invalidClock->calls());
        self::assertSame($invalidBefore, $this->nativeRotationSnapshot($invalidScope->checkpoint));
        self::assertSame(
            2,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\'', [
                $invalidScope->checkpoint,
            ]),
        );
        self::assertSame(0, $this->countEventsForCheckpoint($invalidScope->scopeHash()));

        $mismatchScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-mismatch');
        $this->insertInterruptedAudits($mismatchScope, 1);
        $mismatch = new RotationAuditMismatchConnection($this->connection->getParams(), $this->connection->getDriver());
        $mismatchRejected = false;
        try {
            $this->rotation($mismatch)->rotate($mismatchScope);
        } catch (Throwable) {
            $mismatchRejected = true;
        }
        self::assertTrue($mismatchRejected);
        self::assertSame(
            1,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\'', [
                $mismatchScope->checkpoint,
            ]),
        );
        $this->assertConnectionClosed($mismatch);

        $appendScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-append-fault');
        $this->insertInterruptedAudits($appendScope, 1);
        $appendFault = new RotationOutboxFaultConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            1,
        );
        $appendRejected = false;
        try {
            $this->rotation($appendFault)->rotate($appendScope);
        } catch (Throwable) {
            $appendRejected = true;
        }
        self::assertTrue($appendRejected);
        self::assertSame(
            1,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\'', [
                $appendScope->checkpoint,
            ]),
        );
        self::assertSame(0, $this->countEventsForCheckpoint($appendScope->scopeHash()));
        $appendFault->resetFaults();
        $this->assertConnectionClosed($appendFault);

        $nthScope = $this->scope(StoragePurpose::DeadLetterReason, 'interrupt-append-nth');
        $this->insertInterruptedAudits($nthScope, 2);
        $nthAppendFault = new RotationOutboxFaultConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            2,
        );
        $nthBefore = $this->nativeRotationSnapshot($nthScope->checkpoint);
        try {
            $failure = $this->captureFailure(fn() => $this->rotation($nthAppendFault)->rotate($nthScope));
            self::assertInstanceOf(AuditDeliveryException::class, $failure);
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertSame('storage_unavailable', $failure->code());
            self::assertSame('Audit delivery failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertSame([], $this->eventTypesForCheckpoint($nthScope->scopeHash()));
            self::assertSame(
                2,
                (int) $this->probe->fetchOne('SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'started\'', [
                    $nthScope->checkpoint,
                ]),
            );
            self::assertSame(
                'running',
                (string) $this->probe->fetchOne('SELECT state FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$nthScope->checkpoint]),
            );
            self::assertSame(2, $nthAppendFault->appendAttempts());
            self::assertSame($nthBefore, $this->nativeRotationSnapshot($nthScope->checkpoint));
        } finally {
            $nthAppendFault->resetFaults();
            $this->assertConnectionClosed($nthAppendFault);
        }
    }

    public function testActualCommitThenThrowPreservesEachCommittedNativeAndC3Root(): void
    {
        foreach ([1, 2, 3] as $commitNumber) {
            $record = sprintf('019fca20-0000-7000-8000-%012x', 100 + $commitNumber);
            $checkpoint = 'commit-after-' . $commitNumber;
            $tenant = new TenantRef('org', 'commit-' . $commitNumber);
            $this->insertJournalFixture($record, $tenant, 'commit-' . $commitNumber);
            $fault = new RotationCommitThenThrowConnection(
                $this->connection->getParams(),
                $this->connection->getDriver(),
                $commitNumber,
            );
            $fault->arm();
            $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, $tenant, 2);
            $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            $thrown = false;
            try {
                $this->rotation($fault)->rotate($scope);
            } catch (Throwable) {
                $thrown = true;
            }
            try {
                self::assertTrue($thrown);
                self::assertSame($commitNumber, $fault->commitCount());
                self::assertSame(
                    1,
                    (int) $this->probe->fetchOne('SELECT count(*) FROM '
                    . self::SCHEMA
                    . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]),
                );
                $after = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record]));
                if ($commitNumber === 1) {
                    self::assertSame($before, $after);
                } else {
                    self::assertSame(
                        self::NEW_KEY,
                        new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($after),
                    );
                }
                $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value, failure_fingerprint FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
                self::assertIsArray($checkpointRow);
                $auditRow = $this->probe->fetchAssociative('SELECT state, selected_count, rotated_count, skipped_count, failed_count, failure_fingerprint, finished_at FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
                self::assertIsArray($auditRow);
                $expectedTypes = match ($commitNumber) {
                    1 => ['rotation.storage.started'],
                    2 => ['rotation.storage.started', 'rotation.storage.progressed'],
                    3 => ['rotation.storage.started', 'rotation.storage.progressed', 'rotation.storage.succeeded'],
                };
                self::assertSame($expectedTypes, $this->eventTypesForCheckpoint($scope->scopeHash(), $record));
                if ($commitNumber === 1) {
                    self::assertSame('running', $checkpointRow['state']);
                    self::assertNull($checkpointRow['cursor_value']);
                    self::assertNull($checkpointRow['failure_fingerprint']);
                    self::assertSame('started', $auditRow['state']);
                    self::assertSame('0', (string) $auditRow['selected_count']);
                    self::assertSame('0', (string) $auditRow['rotated_count']);
                    self::assertSame('0', (string) $auditRow['skipped_count']);
                    self::assertSame('0', (string) $auditRow['failed_count']);
                    self::assertNull($auditRow['failure_fingerprint']);
                    self::assertNull($auditRow['finished_at']);
                } elseif ($commitNumber === 2) {
                    self::assertSame('running', $checkpointRow['state']);
                    self::assertSame($record, (string) $checkpointRow['cursor_value']);
                    self::assertNull($checkpointRow['failure_fingerprint']);
                    self::assertSame('started', $auditRow['state']);
                    self::assertSame('1', (string) $auditRow['selected_count']);
                    self::assertSame('1', (string) $auditRow['rotated_count']);
                    self::assertSame('0', (string) $auditRow['skipped_count']);
                    self::assertSame('0', (string) $auditRow['failed_count']);
                    self::assertNull($auditRow['failure_fingerprint']);
                    self::assertNull($auditRow['finished_at']);
                } else {
                    self::assertSame('complete', $checkpointRow['state']);
                    self::assertSame($record, (string) $checkpointRow['cursor_value']);
                    self::assertNull($checkpointRow['failure_fingerprint']);
                    self::assertSame('complete', $auditRow['state']);
                    self::assertSame('1', (string) $auditRow['selected_count']);
                    self::assertSame('1', (string) $auditRow['rotated_count']);
                    self::assertSame('0', (string) $auditRow['skipped_count']);
                    self::assertSame('0', (string) $auditRow['failed_count']);
                    self::assertNull($auditRow['failure_fingerprint']);
                    self::assertNotNull($auditRow['finished_at']);
                }
            } finally {
                $this->assertConnectionClosed($fault);
            }
        }
    }

    public function testBeginRowAndFinishAppendFaultsRollbackOnlyTheirOwnRoot(): void
    {
        foreach ([1, 2, 3] as $appendNumber) {
            $record = sprintf('019fca20-0000-7000-8000-%012x', 200 + $appendNumber);
            $checkpoint = 'append-fault-' . $appendNumber;
            $tenant = new TenantRef('org', 'append-' . $appendNumber);
            $this->insertJournalFixture($record, $tenant, 'append-' . $appendNumber);
            $fault = new RotationOutboxFaultConnection(
                $this->connection->getParams(),
                $this->connection->getDriver(),
                $appendNumber,
            );
            $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, $tenant, 2);
            $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            $thrown = false;
            try {
                $this->rotation($fault)->rotate($scope);
            } catch (Throwable) {
                $thrown = true;
            }
            try {
                self::assertTrue($thrown);
                self::assertSame($appendNumber, $fault->appendAttempts());
                $events = $this->countEventsForCheckpoint($scope->scopeHash(), $record);
                self::assertSame($appendNumber - 1, $events);
                self::assertSame(
                    0,
                    (int) $this->probe->fetchOne('SELECT count(*) FROM '
                    . self::SCHEMA
                    . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'complete\'', [
                        $checkpoint,
                    ]),
                );
                $after = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record]));
                if ($appendNumber < 3) {
                    self::assertSame($before, $after);
                } else {
                    self::assertSame(
                        self::NEW_KEY,
                        new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($after),
                    );
                }
                if ($appendNumber === 1) {
                    self::assertSame(
                        0,
                        (int) $this->probe->fetchOne('SELECT count(*) FROM '
                        . self::SCHEMA
                        . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]),
                    );
                } else {
                    $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value FROM '
                    . self::SCHEMA
                    . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
                    self::assertIsArray($checkpointRow);
                    self::assertSame('running', $checkpointRow['state']);
                    self::assertSame($appendNumber === 2 ? null : $record, $checkpointRow['cursor_value']);
                    $auditRow = $this->probe->fetchAssociative('SELECT state, rotated_count, failed_count FROM '
                    . self::SCHEMA
                    . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
                    self::assertIsArray($auditRow);
                    self::assertSame('started', $auditRow['state']);
                    self::assertSame($appendNumber === 2 ? '0' : '1', (string) $auditRow['rotated_count']);
                    self::assertSame('0', (string) $auditRow['failed_count']);
                }
            } finally {
                $fault->resetFaults();
                $this->assertConnectionClosed($fault);
            }
        }
    }

    public function testBoundedBeginErrorProtectsNativeBeginArguments(): void
    {
        $canary = 'native-trace-begin-canary';
        $scope = $this->scope(StoragePurpose::JournalRecord, $canary, null, 2);
        $this->insertJournalFixture('019fca20-0000-7000-8000-000000000101', null, 'trace-begin');

        $failure = $this->captureTraceFailure(
            fn() => $this->rotation($this->connection, new RotationTraceClock(1, $canary))->rotate($scope),
        );

        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
        $this->assertBoundedNativeTrace(
            $failure,
            $canary,
            [
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit::class,
                    'function' => 'begin',
                    'sensitive' => [0, 1, 2],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'beginAudit',
                    'sensitive' => [0, 1],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'run',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'runInternal',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
            ],
        );
        self::assertSame(
            0,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$canary]),
        );
    }

    public function testBoundedRecoveryErrorProtectsRecoveryArguments(): void
    {
        $canary = 'native-trace-recovery-canary';
        $scope = $this->scope(StoragePurpose::JournalRecord, $canary, null, 2);
        $this->insertInterruptedAudits($scope, 1);

        $failure = $this->captureTraceFailure(
            fn() => $this->rotation($this->connection, new RotationTraceClock(1, $canary))->rotate($scope),
        );

        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
        $this->assertBoundedNativeTrace(
            $failure,
            $canary,
            [
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit::class,
                    'function' => 'recoverInterrupted',
                    'sensitive' => [0, 1, 2],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'beginAudit',
                    'sensitive' => [0, 1],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'run',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'runInternal',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
            ],
        );
        $old = $this->probe->fetchAssociative('SELECT state FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$canary]);
        self::assertIsArray($old);
        self::assertSame('started', $old['state']);
    }

    public function testBoundedProgressErrorProtectsNativeRowArguments(): void
    {
        $canary = 'native-trace-row-canary';
        $record = '019fca20-0000-7000-8000-000000000102';
        $scope = $this->scope(StoragePurpose::JournalRecord, $canary, null, 2);
        $this->insertJournalFixture($record, null, 'trace-row');

        $failure = $this->captureTraceFailure(
            fn() => $this->rotation($this->connection, new RotationTraceClock(2, $canary))->rotate($scope),
        );

        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
        $this->assertBoundedNativeTrace(
            $failure,
            $canary,
            [
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowTransaction::class,
                    'function' => 'execute',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowTransaction::class,
                    'function' => 'transaction',
                    'sensitive' => [0, 1],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowExecutor::class,
                    'function' => 'execute',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'run',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'runInternal',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
            ],
        );
        self::assertSame(
            0,
            (int) $this->probe->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? AND state = \'complete\'', [$canary]),
        );
    }

    public function testBoundedFinishErrorProtectsNativeCompletionArguments(): void
    {
        $canary = 'native-trace-finish-canary';
        $record = '019fca20-0000-7000-8000-000000000103';
        $scope = $this->scope(StoragePurpose::JournalRecord, $canary, null, 2);
        $this->insertJournalFixture($record, null, 'trace-finish');

        $failure = $this->captureTraceFailure(
            fn() => $this->rotation($this->connection, new RotationTraceClock(3, $canary))->rotate($scope),
        );

        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
        $this->assertBoundedNativeTrace(
            $failure,
            $canary,
            [
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit::class,
                    'function' => 'finish',
                    'sensitive' => [0, 1, 2],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'complete',
                    'sensitive' => [0, 1, 2],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationBatch::class,
                    'function' => 'run',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'runInternal',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationEngine::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
                [
                    'class' => \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation::class,
                    'function' => 'rotate',
                    'sensitive' => [0],
                ],
            ],
        );
        $checkpoint = $this->probe->fetchAssociative('SELECT state FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$canary]);
        self::assertIsArray($checkpoint);
        self::assertSame('running', $checkpoint['state']);
    }

    public function testProgressAppendFaultRollsBackOnlyTheCurrentRowRoot(): void
    {
        $record = '019fca20-0000-7000-8000-000000000201';
        $checkpoint = 'append-row-rollback';
        $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
        $this->insertJournalFixture($record, null, 'trace-independent-before');
        $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
        . self::SCHEMA
        . '.journal WHERE record_id = ?', [$record]));
        $fault = new RotationOutboxFaultConnection($this->connection->getParams(), $this->connection->getDriver(), 2);

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertInstanceOf(Throwable::class, $failure);
            self::assertFalse($fault->isTransactionActive());
            self::assertSame(
                $before,
                PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record])),
            );
            $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($checkpointRow);
            self::assertSame('running', $checkpointRow['state']);
            self::assertNull($checkpointRow['cursor_value']);
            self::assertSame(1, $this->countEvents('rotation.storage.started'));
            self::assertSame(0, $this->countEvents('rotation.storage.progressed'));
            self::assertSame(0, $this->countEvents('rotation.storage.succeeded'));
            self::assertSame(0, $fault->finishAttempts());
        } finally {
            $fault->resetFaults();
            $this->assertConnectionClosed($fault);
        }
    }

    public function testFinishAppendFaultPreservesEarlierNativeAndC3Roots(): void
    {
        $record = '019fca20-0000-7000-8000-000000000202';
        $checkpoint = 'append-finish-rollback';
        $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
        $this->insertJournalFixture($record, null, 'finish-append-before');
        $fault = new RotationOutboxFaultConnection($this->connection->getParams(), $this->connection->getDriver(), 3);

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertInstanceOf(Throwable::class, $failure);
            self::assertFalse($fault->isTransactionActive());
            $after = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            self::assertSame(
                self::NEW_KEY,
                new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($after),
            );
            $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($checkpointRow);
            self::assertSame('running', $checkpointRow['state']);
            self::assertSame($record, (string) $checkpointRow['cursor_value']);
            $auditRow = $this->probe->fetchAssociative('SELECT state, rotated_count, failed_count FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($auditRow);
            self::assertSame('started', $auditRow['state']);
            self::assertSame('1', (string) $auditRow['rotated_count']);
            self::assertSame('0', (string) $auditRow['failed_count']);
            self::assertSame(1, $this->countEvents('rotation.storage.started'));
            self::assertSame(1, $this->countEvents('rotation.storage.progressed'));
            self::assertSame(0, $this->countEvents('rotation.storage.succeeded'));
            self::assertSame(0, $this->countEvents('rotation.storage.failed'));
            self::assertSame(1, $fault->finishAttempts());
        } finally {
            $fault->resetFaults();
            $this->assertConnectionClosed($fault);
        }
    }

    public function testRemainingFailureKeepsOriginalWhenFailedAppendRollsBack(): void
    {
        $record = '019fca20-0000-7000-8000-000000000203';
        $checkpoint = 'remaining-failed-append';
        $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
        $this->insertJournalFixture($record, null, 'remaining-before');
        $fault = new RotationRemainingScanOutboxFaultConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            3,
        );

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertSame($fault->remainingFailure(), $failure);
            self::assertSame('d5r2-remaining-scan', $failure->getMessage());
            self::assertFalse($fault->isTransactionActive());
            self::assertSame(1, $fault->remainingScanCalls());
            self::assertSame(1, $fault->finishAttempts());
            self::assertSame(3, $fault->appendAttempts());
            self::assertSame(1, $this->countEvents('rotation.storage.started'));
            self::assertSame(1, $this->countEvents('rotation.storage.progressed'));
            self::assertSame(0, $this->countEvents('rotation.storage.failed'));
            self::assertSame(0, $this->countEvents('rotation.storage.succeeded'));

            $after = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            self::assertSame(
                self::NEW_KEY,
                new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($after),
            );
            $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($checkpointRow);
            self::assertSame('running', $checkpointRow['state']);
            self::assertSame($record, (string) $checkpointRow['cursor_value']);
            $auditRow = $this->probe->fetchAssociative('SELECT state, rotated_count, failed_count FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($auditRow);
            self::assertSame('started', $auditRow['state']);
            self::assertSame('1', (string) $auditRow['rotated_count']);
            self::assertSame('0', (string) $auditRow['failed_count']);
        } finally {
            $fault->resetFaults();
            $this->assertConnectionClosed($fault);
        }
    }

    public function testRollbackFaultBeforeEffectLeavesStartedRootOnly(): void
    {
        $record = '019fca20-0000-7000-8000-000000000204';
        $checkpoint = 'rollback-before-effect';
        $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
        $this->insertJournalFixture($record, null, 'rollback-before');
        $this->corruptJournal($record);
        $before = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
        . self::SCHEMA
        . '.journal WHERE record_id = ?', [$record]));
        $fault = new RotationRollbackFaultConnection($this->connection->getParams(), $this->connection->getDriver());

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertSame('d5r2-rollback-fault', $failure->getMessage());
            self::assertSame(
                $before,
                PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
                . self::SCHEMA
                . '.journal WHERE record_id = ?', [$record])),
            );
            self::assertSame(['rotation.storage.started'], $this->eventTypesForCheckpoint($scope->scopeHash()));
            $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($checkpointRow);
            self::assertSame('running', $checkpointRow['state']);
            self::assertNull($checkpointRow['cursor_value']);
            $auditRow = $this->probe->fetchAssociative('SELECT state, selected_count, rotated_count, failed_count FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($auditRow);
            self::assertSame('started', $auditRow['state']);
            self::assertSame('0', (string) $auditRow['selected_count']);
            self::assertSame('0', (string) $auditRow['rotated_count']);
            self::assertSame('0', (string) $auditRow['failed_count']);
        } finally {
            $fault->resetFaults();
            $this->assertConnectionClosed($fault);
        }
    }

    public function testRollbackFaultAfterEffectLeavesEarlierRowRootOnly(): void
    {
        $record = '019fca20-0000-7000-8000-000000000205';
        $checkpoint = 'rollback-after-effect';
        $scope = $this->scope(StoragePurpose::JournalRecord, $checkpoint, null, 2);
        $this->insertJournalFixture($record, null, 'rollback-after');
        $fault = new RotationRollbackFaultConnection($this->connection->getParams(), $this->connection->getDriver(), 3);

        try {
            $failure = $this->captureFailure(fn() => $this->rotation($fault)->rotate($scope));
            self::assertSame('d5r2-rollback-fault', $failure->getMessage());
            self::assertSame(
                ['rotation.storage.started', 'rotation.storage.progressed'],
                $this->eventTypesForCheckpoint($scope->scopeHash(), $record),
            );
            $after = PostgreSqlBytea::string($this->probe->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$record]));
            self::assertSame(
                self::NEW_KEY,
                new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyId($after),
            );
            $checkpointRow = $this->probe->fetchAssociative('SELECT state, cursor_value, failure_fingerprint FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($checkpointRow);
            self::assertSame('running', $checkpointRow['state']);
            self::assertSame($record, (string) $checkpointRow['cursor_value']);
            self::assertNull($checkpointRow['failure_fingerprint']);
            $auditRow = $this->probe->fetchAssociative('SELECT state, selected_count, rotated_count, failed_count, finished_at FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ?', [$checkpoint]);
            self::assertIsArray($auditRow);
            self::assertSame('started', $auditRow['state']);
            self::assertSame('1', (string) $auditRow['selected_count']);
            self::assertSame('1', (string) $auditRow['rotated_count']);
            self::assertSame('0', (string) $auditRow['failed_count']);
            self::assertNull($auditRow['finished_at']);
        } finally {
            $fault->resetFaults();
            $this->assertConnectionClosed($fault);
        }
    }

    private function rotation(
        Connection $connection,
        ?ClockInterface $clock = null,
    ): PostgreSqlStorageProtectionRotation {
        return new PostgreSqlStorageProtectionRotation(
            $connection,
            new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY)),
            self::SCHEMA,
            $this->hook($connection, $clock),
        );
    }

    private function hook(Connection $connection, ?ClockInterface $clock = null): StorageRotationAuditHook
    {
        $clock ??= new RotationClock(new DateTimeImmutable(self::TIME));
        $bundle = $this->auditBundle($connection);
        $writer = new StorageRotationAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('r', 32)),
            $bundle['selector'],
            $bundle['delivery'],
            $clock,
            new ActorContext(
                new ActorRef('rotation-origin', 'service'),
                new ActorRef('rotation-actor', 'operator'),
                new ActorRef('rotation-execution', 'storage'),
            ),
            AuditRecordCorrelation::fromArray(['correlationId' => 'rotation-correlation']),
            AuditSourceRuntime::Worker,
            self::BUILD_ID,
        );

        return new StorageRotationAuditHook($writer);
    }

    /** @return array{selector:AuditDeliverySelector,delivery:AuditDeliveryCoordinator} */
    private function auditBundle(Connection $connection): array
    {
        $jcs = new AuditJcsCodec();
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $partitions = new AuditPartitionIdDeriver($jcs);
        $finalizer = new AuditRecordFinalizer($validator, $records);
        $ingress = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $this->protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new RotationAuditKeySource(),
            new RotationNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new RotationClock(new DateTimeImmutable(self::TIME));
        $uuids = new RotationUuidGenerator($this->storeIds());
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $this->recordSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $this->protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $connection,
            $this->outboxSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $this->protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator()),
            new AuditOutboxConfiguration(),
        );
        $selector = new AuditDeliverySelector($lookup, $validator);

        return [
            'selector' => $selector,
            'delivery' => new AuditDeliveryCoordinator($connection, $selector, $recordStore, $outboxStore),
        ];
    }

    /** @return list<string> */
    private function storeIds(): array
    {
        $batch = $this->uuidBatch++;
        $ids = [];
        for ($index = 1; $index <= 512; ++$index) {
            $ids[] = sprintf('019fca20-%04x-7000-8000-%012x', $batch, $index);
        }

        return $ids;
    }

    private function scope(
        StoragePurpose $purpose,
        string $checkpoint,
        ?TenantRef $tenant = null,
        int $batchSize = 2,
    ): StorageProtectionRotationScope {
        return new StorageProtectionRotationScope(
            $purpose,
            $tenant,
            self::OLD_KEY,
            self::NEW_KEY,
            $batchSize,
            $checkpoint,
            'native-actor',
            'native-reason',
            true,
        );
    }

    private function insertJournalFixture(string $record, ?TenantRef $tenant, string $plain): void
    {
        $operation = $this->operationFor($record);
        $envelope = $this->oldEnvelope(StoragePurpose::JournalRecord, $record, $tenant, $plain);
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.journal (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record, tenant_type, tenant_id) VALUES (?, ?, ?, 1, \'received\', 1, 7, ?, ?, ?, ?)',
            [
                $record,
                $operation,
                self::OPERATION_TYPE,
                '2026-09-29 00:00:00+00',
                $envelope,
                $tenant?->type(),
                $tenant?->id(),
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
    }

    private function insertPurposeFixtures(): void
    {
        $old = new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::OLD_KEY));
        $operation = self::OPERATION;
        $record = self::RECORD;
        $type = self::OPERATION_TYPE;
        $now = '2026-09-29 00:00:00+00';
        $scopeHash = hash('sha256', 'rotation-purpose-matrix');
        $contexts = [
            StoragePurpose::JournalRecord->value => new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $record,
                $operation,
                $type,
                7,
            ),
            StoragePurpose::DeferredPayload->value => new StorageProtectionContext(
                StoragePurpose::DeferredPayload,
                $operation . ':payload',
                $operation,
                $type,
                7,
            ),
            StoragePurpose::DeferredContext->value => new StorageProtectionContext(
                StoragePurpose::DeferredContext,
                $operation . ':context',
                $operation,
                $type,
                7,
            ),
            StoragePurpose::OutcomePayload->value => new StorageProtectionContext(
                StoragePurpose::OutcomePayload,
                $operation,
                $operation,
                $type,
                7,
            ),
            StoragePurpose::OutboxPayload->value => new StorageProtectionContext(
                StoragePurpose::OutboxPayload,
                $record,
                $operation,
                $type,
                7,
            ),
            StoragePurpose::OutboxContext->value => new StorageProtectionContext(
                StoragePurpose::OutboxContext,
                $record,
                $operation,
                $type,
                7,
            ),
            StoragePurpose::DeadLetterReason->value => new StorageProtectionContext(
                StoragePurpose::DeadLetterReason,
                $operation,
                $operation,
                $type,
                7,
            ),
            StoragePurpose::IdempotencyResponse->value => new StorageProtectionContext(
                StoragePurpose::IdempotencyResponse,
                '1:' . $scopeHash,
                $operation,
                $type,
                3,
            ),
            StoragePurpose::IdempotencyResult->value => new StorageProtectionContext(
                StoragePurpose::IdempotencyResult,
                '1:' . $scopeHash,
                $operation,
                $type,
                3,
            ),
        ];
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.operations (operation_id, operation_type, schema_version, encoded_payload, encoded_context, content_type, encoding, state, state_version, next_sequence, available_at, accepted_at, created_at, updated_at) VALUES (?, ?, 7, ?, ?, ?, ?, \'accepted\', 1, 1, ?, ?, ?, ?)',
            [
                $operation,
                $type,
                $old->encrypt('payload', $contexts[StoragePurpose::DeferredPayload->value]),
                $old->encrypt('context', $contexts[StoragePurpose::DeferredContext->value]),
                'application/json',
                'json',
                $now,
                $now,
                $now,
                $now,
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.journal (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (?, ?, ?, 1, \'received\', 1, 7, ?, ?)',
            [
                $record,
                $operation,
                $type,
                $now,
                $old->encrypt('journal', $contexts[StoragePurpose::JournalRecord->value]),
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outcomes (operation_id, outcome_type, schema_version, encoded_payload, completed_at) VALUES (?, \'completed\', 99, ?, ?)',
            [$operation, $old->encrypt('outcome', $contexts[StoragePurpose::OutcomePayload->value]), $now],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING],
        );
        $this->connection->executeStatement(
            'INSERT INTO ' . self::SCHEMA . '.dead_letters (operation_id, encoded_reason, moved_at) VALUES (?, ?, ?)',
            [$operation, $old->encrypt('reason', $contexts[StoragePurpose::DeadLetterReason->value]), $now],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outbox_records (record_id, operation_id, operation_type, schema_version, encoded_payload, encoded_context, content_type, encoding, available_at, recorded_at, connection_name) VALUES (?, ?, ?, 7, ?, ?, ?, ?, ?, ?, ?)',
            [
                $record,
                $operation,
                $type,
                $old->encrypt('outbox-payload', $contexts[StoragePurpose::OutboxPayload->value]),
                $old->encrypt('outbox-context', $contexts[StoragePurpose::OutboxContext->value]),
                'application/json',
                'json',
                $now,
                $now,
                'default',
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.idempotency_records (scope_version, scope_hash, key_version, key_hash, fingerprint_version, fingerprint_hash, operation_id, strategy, state, created_at, expires_at, operation_type, application_schema_version, encoded_response, encoded_result) VALUES (1, ?, 1, ?, 1, ?, ?, \'sync\', \'terminal\', ?, ?, ?, 3, ?, ?)',
            [
                $scopeHash,
                hash('sha256', 'rotation-key'),
                hash('sha256', 'rotation-fingerprint'),
                $operation,
                $now,
                '2027-09-29 00:00:00+00',
                $type,
                $old->encrypt('response', $contexts[StoragePurpose::IdempotencyResponse->value]),
                $old->encrypt('result', $contexts[StoragePurpose::IdempotencyResult->value]),
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
            ],
        );
    }

    private function oldEnvelope(StoragePurpose $purpose, string $record, ?TenantRef $tenant, string $plain): string
    {
        return $this->envelope($purpose, $record, $tenant, $plain, self::OLD_KEY);
    }

    private function newEnvelope(StoragePurpose $purpose, string $record, ?TenantRef $tenant, string $plain): string
    {
        return $this->envelope($purpose, $record, $tenant, $plain, self::NEW_KEY);
    }

    private function envelope(
        StoragePurpose $purpose,
        string $record,
        ?TenantRef $tenant,
        string $plain,
        string $keyId,
    ): string {
        $operation = $this->operationFor($record);
        $identity = match ($purpose) {
            StoragePurpose::JournalRecord => $record,
            StoragePurpose::DeferredPayload => $operation . ':payload',
            StoragePurpose::DeferredContext => $operation . ':context',
            StoragePurpose::OutboxPayload, StoragePurpose::OutboxContext => $record,
            StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult => '1:'
                . hash('sha256', 'rotation-purpose-matrix'),
            default => $operation,
        };
        $schema = in_array($purpose, [StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult], true)
            ? 3
            : 7;

        return new BopdEnvelopeCodec(new RotationStorageKeyProvider($keyId))->encrypt(
            $plain,
            new StorageProtectionContext($purpose, $identity, $operation, self::OPERATION_TYPE, $schema, $tenant),
        );
    }

    private function operationFor(string $record): string
    {
        if ($record === self::RECORD) {
            return self::OPERATION;
        }

        $suffix = (int) hexdec(substr($record, -12));

        return substr($record, 0, -12) . sprintf('%012x', $suffix + 0x1000);
    }

    private function purposeCursor(StoragePurpose $purpose): string
    {
        return match ($purpose) {
            StoragePurpose::JournalRecord => self::RECORD,
            StoragePurpose::DeferredPayload => self::OPERATION,
            StoragePurpose::DeferredContext => self::OPERATION,
            StoragePurpose::OutcomePayload => self::OPERATION,
            StoragePurpose::OutboxPayload => self::RECORD,
            StoragePurpose::OutboxContext => self::RECORD,
            StoragePurpose::DeadLetterReason => self::OPERATION,
            StoragePurpose::IdempotencyResponse => '1:' . hash('sha256', 'rotation-purpose-matrix'),
            StoragePurpose::IdempotencyResult => '1:' . hash('sha256', 'rotation-purpose-matrix'),
        };
    }

    private function purposeEnvelope(StoragePurpose $purpose): string
    {
        [$table, $column, $where, $parameter] = match ($purpose) {
            StoragePurpose::JournalRecord => ['journal', 'encoded_record', 'record_id = ?', self::RECORD],
            StoragePurpose::DeferredPayload => ['operations', 'encoded_payload', 'operation_id = ?', self::OPERATION],
            StoragePurpose::DeferredContext => ['operations', 'encoded_context', 'operation_id = ?', self::OPERATION],
            StoragePurpose::OutcomePayload => ['outcomes', 'encoded_payload', 'operation_id = ?', self::OPERATION],
            StoragePurpose::OutboxPayload => ['outbox_records', 'encoded_payload', 'record_id = ?', self::RECORD],
            StoragePurpose::OutboxContext => ['outbox_records', 'encoded_context', 'record_id = ?', self::RECORD],
            StoragePurpose::DeadLetterReason => ['dead_letters', 'encoded_reason', 'operation_id = ?', self::OPERATION],
            StoragePurpose::IdempotencyResponse => [
                'idempotency_records',
                'encoded_response',
                'scope_hash = ?',
                hash('sha256', 'rotation-purpose-matrix'),
            ],
            StoragePurpose::IdempotencyResult => [
                'idempotency_records',
                'encoded_result',
                'scope_hash = ?',
                hash('sha256', 'rotation-purpose-matrix'),
            ],
        };

        return PostgreSqlBytea::string($this->connection->fetchOne('SELECT '
        . $column
        . ' FROM '
        . self::SCHEMA
        . '.'
        . $table
        . ' WHERE '
        . $where, [$parameter]));
    }

    private function purposeKey(StoragePurpose $purpose): string
    {
        $header = substr($this->purposeEnvelope($purpose), 0, 136);

        return new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->keyIdFromHeader($header);
    }

    private function purposePlaintext(StoragePurpose $purpose): string
    {
        $expected = match ($purpose) {
            StoragePurpose::JournalRecord => 'journal',
            StoragePurpose::DeferredPayload => 'payload',
            StoragePurpose::DeferredContext => 'context',
            StoragePurpose::OutcomePayload => 'outcome',
            StoragePurpose::OutboxPayload => 'outbox-payload',
            StoragePurpose::OutboxContext => 'outbox-context',
            StoragePurpose::DeadLetterReason => 'reason',
            StoragePurpose::IdempotencyResponse => 'response',
            StoragePurpose::IdempotencyResult => 'result',
        };
        $operation = self::OPERATION;
        $identity = match ($purpose) {
            StoragePurpose::JournalRecord => self::RECORD,
            StoragePurpose::DeferredPayload => $operation . ':payload',
            StoragePurpose::DeferredContext => $operation . ':context',
            StoragePurpose::OutboxPayload, StoragePurpose::OutboxContext => self::RECORD,
            StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult => '1:'
                . hash('sha256', 'rotation-purpose-matrix'),
            default => $operation,
        };
        $schema = in_array($purpose, [StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult], true)
            ? 3
            : 7;
        $actual = new BopdEnvelopeCodec(new RotationStorageKeyProvider(self::NEW_KEY))->decrypt(
            $this->purposeEnvelope($purpose),
            new StorageProtectionContext($purpose, $identity, $operation, self::OPERATION_TYPE, $schema),
        );
        self::assertSame($expected, $actual);

        return $actual;
    }

    private function corruptJournal(string $record): void
    {
        $valid = PostgreSqlBytea::string($this->connection->fetchOne('SELECT encoded_record FROM '
        . self::SCHEMA
        . '.journal WHERE record_id = ?', [$record]));
        $tampered = substr_replace($valid, (string) chr(ord($valid[strlen($valid) - 1]) ^ 1), -1, 1);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = ? WHERE record_id = ?',
            [$tampered, $record],
            [ParameterType::BINARY, ParameterType::STRING],
        );
    }

    private function insertInterruptedAudits(
        StorageProtectionRotationScope $scope,
        int $count,
        bool $invalidId = false,
        ?int $invalidIndex = null,
    ): void {
        $now = '2026-09-29 00:00:00+00';
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.storage_protection_rotation_checkpoints (checkpoint_id, scope_hash, state, cursor_value, failure_fingerprint, updated_at) VALUES (?, ?, \'running\', NULL, NULL, ?)',
            [$scope->checkpoint, $scope->scopeHash(), $now],
        );
        $invalidAt = $invalidId ? $invalidIndex ?? 0 : null;
        for ($index = 0; $index < $count; ++$index) {
            $auditId = $invalidAt === $index ? 'not-a-valid-audit-id' : $this->interruptedAuditId($scope, $index);
            $this->connection->executeStatement(
                'INSERT INTO '
                . self::SCHEMA
                . '.storage_protection_rotation_audits (audit_id, checkpoint_id, scope_hash, purpose, old_key_id, new_key_id, actor, reason, state, started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'started\', ?)',
                [
                    $auditId,
                    $scope->checkpoint,
                    $scope->scopeHash(),
                    $scope->purpose->value,
                    self::OLD_KEY,
                    self::NEW_KEY,
                    'fixture',
                    'fixture',
                    $now,
                ],
            );
        }
    }

    /** @return array{checkpoint:array<string,mixed>|null,audits:list<array<string,mixed>>} */
    private function nativeRotationSnapshot(string $checkpoint): array
    {
        $checkpointRow = $this->probe->fetchAssociative('SELECT checkpoint_id, scope_hash, state, cursor_value, failure_fingerprint, updated_at FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);

        return [
            'checkpoint' => $checkpointRow === false ? null : $checkpointRow,
            'audits' => $this->probe->fetchAllAssociative('SELECT audit_id, checkpoint_id, scope_hash, purpose, old_key_id, new_key_id, actor, reason, state, '
            . 'selected_count, rotated_count, skipped_count, failed_count, failure_fingerprint, started_at, finished_at '
            . 'FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = ? ORDER BY audit_id', [$checkpoint]),
        ];
    }

    private function interruptedAuditId(StorageProtectionRotationScope $scope, int $index): string
    {
        return substr(hash('sha256', $scope->checkpoint . ':' . $index), 0, 32);
    }

    /**
     * @return list<array{
     *     logicalRecord:array<string,mixed>,
     *     tenantBinding:mixed,
     *     partitionId:string,
     *     idempotencyKeyHash:string,
     *     recordId:string,
     *     ingressOrdinal:int
     * }>
     */
    private function logicalOutboxRows(): array
    {
        $rows = $this->probe->fetchAllAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, ingress_ordinal::text AS ingress_ordinal, idempotency_key_hash, content_hash, protected_body FROM '
            . $this->outboxSchema->recordsTable()
            . ' AS audit_records ORDER BY audit_records.ingress_ordinal',
        );
        $result = [];
        foreach ($rows as $row) {
            $tenant = $row['tenant_type'] === null
                ? null
                : ['type' => (string) $row['tenant_type'], 'id' => (string) $row['tenant_id']];
            $ingress = AuditProtectedIngress::fromArray([
                'recordId' => (string) $row['record_id'],
                'partitionId' => (string) $row['partition_id'],
                'tenantBinding' => $tenant,
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
                'idempotencyKeyHash' => (string) $row['idempotency_key_hash'],
                'contentHash' => (string) $row['content_hash'],
                'protectedBody' => PostgreSqlBytea::string($row['protected_body']),
            ]);
            $envelope = $this->protection->unprotectOutbox($ingress)->toArray();
            $result[] = [
                'logicalRecord' => $envelope['logicalRecord'],
                'tenantBinding' => $tenant,
                'partitionId' => (string) $row['partition_id'],
                'idempotencyKeyHash' => (string) $row['idempotency_key_hash'],
                'recordId' => (string) $row['record_id'],
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
            ];
        }

        return $result;
    }

    private function partitionForTenant(?TenantRef $tenant): string
    {
        $reference = $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id());

        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($reference);
    }

    /** @param array<string,mixed> $row */
    private function assertPersistedEventIdentity(
        array $row,
        string $auditId,
        string $scopeHash,
        ?string $cursor,
        ?TenantRef $tenant,
    ): void {
        $logical = $row['logicalRecord'];
        self::assertIsArray($logical);
        $event = $logical['event'] ?? null;
        self::assertIsArray($event);
        $eventType = $event['type'] ?? null;
        self::assertIsString($eventType);
        $expectedActionContext = 'rotation-storage:' . $eventType . ':' . $auditId;
        $expectedResourceContext = 'rotation-storage:scope:' . $scopeHash;
        if ($eventType === 'rotation.storage.progressed') {
            self::assertIsString($cursor);
            $expectedActionContext .= ':' . $cursor;
            $expectedResourceContext = 'rotation-storage:row:' . $scopeHash . ':' . $cursor;
        } else {
            self::assertNull($cursor);
        }

        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('r', 32));
        $expectedActionId = (string) $deriver->derive(AuditOpaqueIdPurpose::Action, $expectedActionContext);
        $expectedResourceId = (string) $deriver->derive(AuditOpaqueIdPurpose::Resource, $expectedResourceContext);
        $action = $logical['action'] ?? null;
        $resource = $logical['resource'] ?? null;
        self::assertIsArray($action);
        self::assertIsArray($resource);
        self::assertSame($expectedActionId, $action['id'] ?? null);
        self::assertSame($eventType, $action['type'] ?? null);
        self::assertSame($expectedResourceId, $resource['id'] ?? null);
        self::assertSame('protected_storage', $resource['type'] ?? null);

        $expectedTenant = $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()];
        self::assertSame($expectedTenant, $row['tenantBinding']);
        self::assertSame($expectedTenant, $logical['tenant'] ?? null);
        $partition = $this->partitionForTenant($tenant);
        self::assertSame($partition, $row['partitionId']);
        self::assertSame(
            new AuditOutboxIdempotencyKeyHasher()->hash($partition, 'rotation-storage:' . $expectedActionId),
            $row['idempotencyKeyHash'],
        );
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D',
            $row['recordId'],
        );
        self::assertGreaterThan(0, $row['ingressOrdinal']);
    }

    /** @param array<string,mixed> $logical */
    private function assertRotationLogicalTuple(array $logical, string $eventType): void
    {
        [$phase, $status, $code] = match ($eventType) {
            'rotation.storage.started' => ['started', 'unknown', 'rotation_started'],
            'rotation.storage.progressed' => ['committed', 'unknown', 'rotation_progressed'],
            'rotation.storage.succeeded' => ['succeeded', 'succeeded', 'rotation_succeeded'],
            'rotation.storage.failed' => ['failed', 'failed', 'rotation_failed'],
            default => self::fail('Unexpected persisted rotation event.'),
        };
        self::assertSame(
            [
                'type' => $eventType,
                'version' => 1,
                'category' => 'rotation',
                'catalogHash' => FrameworkOperationalAuditEventCatalog::catalog()->catalogHash,
            ],
            $logical['event'] ?? null,
        );
        $action = $logical['action'] ?? null;
        self::assertIsArray($action);
        self::assertSame(['id' => $action['id'] ?? null, 'type' => $eventType, 'phase' => $phase], $action);
        self::assertSame(['status' => $status, 'code' => $code], $logical['outcome'] ?? null);
        self::assertSame(
            [
                'id' => 'blackops.rotation.storage',
                'version' => 1,
                'decision' => 'not_applicable',
                'reasonCode' => $code,
                'auditDelivery' => 'durable',
            ],
            $logical['policy'] ?? null,
        );
        self::assertSame(['occurredAt' => self::TIME, 'recordedAt' => self::TIME], $logical['time'] ?? null);
        self::assertSame(
            [
                'owner' => 'framework',
                'component' => 'blackops.rotation.storage',
                'runtime' => 'worker',
                'buildId' => self::BUILD_ID,
            ],
            $logical['source'] ?? null,
        );
        self::assertSame(
            [
                'principal' => ['type' => 'operator', 'id' => 'rotation-actor'],
                'execution' => ['type' => 'storage', 'id' => 'rotation-execution'],
            ],
            $logical['actor'] ?? null,
        );
        self::assertSame(['correlationId' => 'rotation-correlation'], $logical['correlation'] ?? null);
        self::assertSame(['version' => 1], $logical['evidence'] ?? null);
        $encoded = json_encode($logical, JSON_THROW_ON_ERROR);
        foreach ([self::OLD_KEY, self::NEW_KEY, 'native-actor', 'native-reason'] as $secret) {
            self::assertStringNotContainsString($secret, $encoded);
        }
    }

    private function countEvents(?string $type = null): int
    {
        $rows = $this->logicalOutboxRows();
        if ($type === null) {
            return count($rows);
        }

        return count(array_filter(
            $rows,
            static fn(array $row): bool => $row['logicalRecord']['event']['type'] === $type,
        ));
    }

    /** @return list<array<string,mixed>> */
    private function eventsForCheckpoint(string $scopeHash, ?string $cursor = null): array
    {
        $all = $this->logicalOutboxRows();
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('r', 32));
        $resources = [
            (string) $deriver->derive(AuditOpaqueIdPurpose::Resource, 'rotation-storage:scope:' . $scopeHash),
        ];
        if ($cursor !== null) {
            $resources[] = (string) $deriver->derive(
                AuditOpaqueIdPurpose::Resource,
                'rotation-storage:row:' . $scopeHash . ':' . $cursor,
            );
        }
        return array_values(array_filter($all, static function (array $row) use ($resources): bool {
            $resource = $row['logicalRecord']['resource']['id'] ?? '';
            return is_string($resource) && in_array($resource, $resources, true);
        }));
    }

    private function countEventsForCheckpoint(string $scopeHash, ?string $cursor = null): int
    {
        return count($this->eventsForCheckpoint($scopeHash, $cursor));
    }

    /** @return list<string> */
    private function eventTypesForCheckpoint(string $scopeHash, ?string $cursor = null): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['logicalRecord']['event']['type'],
            $this->eventsForCheckpoint($scopeHash, $cursor),
        );
    }

    /**
     * @param list<array{class:class-string,function:string,sensitive:list<int>}> $expected
     */
    private function assertBoundedNativeTrace(Throwable $failure, string $canary, array $expected): void
    {
        self::assertInstanceOf(AuditDeliveryException::class, $failure);
        self::assertNull($failure->getPrevious());

        $trace = $failure->getTrace();
        $found = [];
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $function = $frame['function'] ?? null;
            if (!is_string($class) || !is_string($function)) {
                continue;
            }
            $args = $frame['args'] ?? [];
            self::assertIsArray($args);
            $this->assertNoScalarCanary($args, $canary);
            foreach ($expected as $wanted) {
                if ($wanted['class'] !== $class || $wanted['function'] !== $function) {
                    continue;
                }
                $key = $class . '::' . $function;
                $found[$key] = true;
                self::assertNotSame([], $args, $key . ' must expose trace arguments.');
                foreach ($wanted['sensitive'] as $index) {
                    self::assertArrayHasKey($index, $args, $key . ' must expose argument ' . $index . '.');
                    self::assertInstanceOf(
                        \SensitiveParameterValue::class,
                        $args[$index],
                        $key . ' argument ' . $index,
                    );
                }
            }
        }
        foreach ($expected as $wanted) {
            $key = $wanted['class'] . '::' . $wanted['function'];
            self::assertTrue($found[$key] ?? false, $key . ' must be present in the bounded error trace.');
        }
    }

    private function assertNoScalarCanary(mixed $value, string $canary): void
    {
        if (is_string($value)) {
            self::assertStringNotContainsString($canary, $value);
            return;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertNoScalarCanary($item, $canary);
            }
        }
    }

    private function captureTraceFailure(callable $operation): Throwable
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            return $this->captureFailure($operation);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    private function captureFailure(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }
        self::fail('The operation was expected to throw.');
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
        if (!$connection->isAutoCommit()) {
            $connection->setAutoCommit(true);
        }
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    private function assertConnectionClosed(Connection $connection): void
    {
        $this->restoreConnection($connection);
        $connection->close();
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class RotationStorageKeyProvider implements StorageKeyProvider
{
    public function __construct(
        private string $active,
    ) {}

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($this->active, str_repeat($this->active === 'old:v1' ? 'o' : 'n', 32));
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat($keyId === 'old:v1' ? 'o' : 'n', 32));
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class RotationClock implements ClockInterface
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
final class RotationTraceClock implements ClockInterface
{
    private int $calls = 0;

    public function __construct(
        private readonly int $failAt,
        private readonly string $canary,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->calls === $this->failAt) {
            throw new \RuntimeException($this->canary);
        }

        return new DateTimeImmutable('2026-09-29T12:00:00.000000Z');
    }

    public function calls(): int
    {
        return $this->calls;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationNonceSource implements NonceSource
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;

        return substr(hash('sha256', 'rotation-nonce-' . $this->counter, true), 0, 24);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class RotationAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey(
            $purpose === AuditProtectionPurpose::AuditRecord ? 'audit-record' : 'audit-outbox',
            str_repeat($purpose === AuditProtectionPurpose::AuditRecord ? 'r' : 'o', 32),
        );
    }

    public function key(
        string $keyId,
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey($keyId, str_repeat($purpose === AuditProtectionPurpose::AuditRecord ? 'r' : 'o', 32));
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationUuidGenerator implements Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        $value = array_shift($this->ids);
        if (!is_string($value)) {
            throw new \RuntimeException('rotation audit UUID sequence exhausted');
        }

        return $value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationCommitThenThrowConnection extends Connection
{
    private int $commits = 0;
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

    public function commitCount(): int
    {
        return $this->commits;
    }

    public function commit(): void
    {
        parent::commit();
        ++$this->commits;
        if ($this->armed && $this->commits === $this->throwAt) {
            $this->armed = false;
            throw new \RuntimeException('commit response lost after durable commit');
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationRollbackFaultConnection extends Connection
{
    private int $commits = 0;
    private bool $rollbackFaultArmed = true;

    public function __construct(
        array $params,
        Driver $driver,
        private ?int $throwBeforeCommitAt = null,
    ) {
        parent::__construct($params, $driver);
    }

    public function commit(): void
    {
        ++$this->commits;
        if ($this->throwBeforeCommitAt !== null && $this->commits === $this->throwBeforeCommitAt) {
            throw new \RuntimeException('d5r2-commit-before');
        }

        parent::commit();
    }

    public function rollBack(): void
    {
        if ($this->rollbackFaultArmed) {
            $this->rollbackFaultArmed = false;
            throw new \RuntimeException('d5r2-rollback-fault');
        }

        parent::rollBack();
    }

    public function resetFaults(): void
    {
        $this->rollbackFaultArmed = false;
        $this->throwBeforeCommitAt = null;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationOutboxFaultConnection extends Connection
{
    private int $appendCount = 0;
    private int $finishAttemptCount = 0;

    public function __construct(
        array $params,
        Driver $driver,
        private ?int $throwAt,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if (str_contains($query, 'audit_outbox_records') && str_contains($query, 'INSERT')) {
            ++$this->appendCount;
            if ($this->throwAt !== null && $this->appendCount === $this->throwAt) {
                throw new \RuntimeException('configured audit append fault');
            }
        }

        return parent::fetchOne($query, $params, $types);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (str_contains($sql, 'SET state = ?, failure_fingerprint = ?')) {
            ++$this->finishAttemptCount;
        }

        return parent::executeStatement($sql, $params, $types);
    }

    public function finishAttempts(): int
    {
        return $this->finishAttemptCount;
    }

    public function appendAttempts(): int
    {
        return $this->appendCount;
    }

    public function resetFaults(): void
    {
        $this->throwAt = null;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationRemainingScanOutboxFaultConnection extends Connection
{
    private int $appendCount = 0;
    private int $finishAttemptCount = 0;
    private int $remainingScanCount = 0;
    private bool $remainingFaulted = false;
    private ?Throwable $remainingFailure = null;

    public function __construct(
        array $params,
        Driver $driver,
        private ?int $appendFaultAt,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if (str_contains($query, 'audit_outbox_records') && str_contains($query, 'INSERT')) {
            ++$this->appendCount;
            if ($this->appendFaultAt !== null && $this->appendCount === $this->appendFaultAt) {
                throw new \RuntimeException('d5r2-failed-finish-append');
            }
        }

        return parent::fetchOne($query, $params, $types);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (str_contains($sql, 'SET state = ?, failure_fingerprint = ?')) {
            ++$this->finishAttemptCount;
        }

        return parent::executeStatement($sql, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        if (str_contains($query, 'SELECT substring(')) {
            ++$this->remainingScanCount;
            if (!$this->remainingFaulted) {
                $this->remainingFaulted = true;
                $this->remainingFailure = new \RuntimeException('d5r2-remaining-scan');
                throw $this->remainingFailure;
            }
        }

        return parent::iterateAssociative($query, $params, $types);
    }

    public function appendAttempts(): int
    {
        return $this->appendCount;
    }

    public function finishAttempts(): int
    {
        return $this->finishAttemptCount;
    }

    public function remainingScanCalls(): int
    {
        return $this->remainingScanCount;
    }

    public function remainingFailure(): ?Throwable
    {
        return $this->remainingFailure;
    }

    public function resetFaults(): void
    {
        $this->appendFaultAt = null;
        $this->remainingFaulted = false;
        $this->remainingFailure = null;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationSubjectFaultConnection extends Connection
{
    private bool $armed = false;
    private string $mode = 'tenant';

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function arm(string $mode): void
    {
        $this->armed = true;
        $this->mode = $mode;
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $rows = parent::fetchAllAssociative($query, $params, $types);
        $journalRow = str_contains($query, 'journal') && str_contains($query, 'encoded_record');
        $compositeRow = str_contains($query, 'idempotency_records') && str_contains($query, 'encoded_response');
        if ($this->armed && ($journalRow || $compositeRow)) {
            $this->armed = false;
            if ($rows !== []) {
                if ($this->mode === 'tenant') {
                    $rows[0]['tenant_type'] = 'org';
                    $rows[0]['tenant_id'] = null;
                } elseif ($this->mode === 'tenant-missing') {
                    unset($rows[0]['tenant_id']);
                } elseif ($this->mode === 'tenant-wrong-type') {
                    $rows[0]['tenant_type'] = ['org'];
                    $rows[0]['tenant_id'] = 'tenant-form';
                } elseif ($this->mode === 'tenant-whitespace') {
                    $rows[0]['tenant_type'] = ' org';
                    $rows[0]['tenant_id'] = 'tenant-form';
                } elseif ($this->mode === 'tenant-control') {
                    $rows[0]['tenant_type'] = "org\u{0001}";
                    $rows[0]['tenant_id'] = 'tenant-form';
                } elseif ($this->mode === 'tenant-nfc') {
                    $rows[0]['tenant_type'] = 'org';
                    $rows[0]['tenant_id'] = "te\u{0301}nant-form";
                } elseif ($this->mode === 'tenant-length') {
                    $rows[0]['tenant_type'] = 'org';
                    $rows[0]['tenant_id'] = str_repeat('x', 257);
                } elseif ($this->mode === 'tenant-mismatch') {
                    $rows[0]['tenant_type'] = 'org';
                    $rows[0]['tenant_id'] = 'tenant-other';
                } elseif ($this->mode === 'composite-overflow') {
                    $rows[0]['scope_version'] = '2147483648';
                } else {
                    $rows[0]['record_id'] = 'not-a-canonical-uuid';
                }
                $rows[0]['encoded_record'] = 'corrupt';
                if ($compositeRow) {
                    $rows[0]['encoded_response'] = 'corrupt';
                }
            }
        }

        return $rows;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationCasRaceConnection extends Connection
{
    private bool $armed = true;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly Connection $racer,
        private readonly string $journalTable,
        private readonly string $record,
        private readonly string $replacement,
        private readonly ?TenantRef $replacementTenant = null,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $rows = parent::fetchAllAssociative($query, $params, $types);
        if ($this->armed && str_contains($query, 'encoded_record') && str_contains($query, $this->journalTable)) {
            $this->armed = false;
            $this->racer->executeStatement(
                'UPDATE '
                . $this->journalTable
                . ' SET encoded_record = ?, tenant_type = ?, tenant_id = ? WHERE record_id = ?',
                [$this->replacement, $this->replacementTenant?->type(), $this->replacementTenant?->id(), $this->record],
                [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
        }

        return $rows;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationSelectedKeyMismatchConnection extends Connection
{
    private bool $armed = true;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly string $journalTable,
        private readonly string $selectedSnapshot,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $rows = parent::fetchAllAssociative($query, $params, $types);
        if ($this->armed && str_contains($query, 'encoded_record') && str_contains($query, $this->journalTable)) {
            $this->armed = false;
            if ($rows !== []) {
                $rows[0]['encoded_record'] = $this->selectedSnapshot;
            }
        }

        return $rows;
    }

    public function injected(): bool
    {
        return !$this->armed;
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationAuditMismatchConnection extends Connection
{
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (str_contains($sql, 'storage_protection_rotation_audits') && str_contains($sql, "SET state='failed'")) {
            return 0;
        }

        return parent::executeStatement($sql, $params, $types);
    }
}

/** @mago-expect lint:single-class-per-file */
final class RotationWorkCountingConnection extends Connection
{
    private int $sqlCalls = 0;
    private int $advisoryCalls = 0;
    private int $transactionCalls = 0;

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->countSql($query);

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->countSql($query);

        return parent::fetchOne($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->countSql($query);

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        $this->countSql($query);

        return parent::iterateAssociative($query, $params, $types);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->countSql($sql);

        return parent::executeStatement($sql, $params, $types);
    }

    public function beginTransaction(): void
    {
        ++$this->transactionCalls;
        parent::beginTransaction();
    }

    public function commit(): void
    {
        ++$this->transactionCalls;
        parent::commit();
    }

    public function rollBack(): void
    {
        ++$this->transactionCalls;
        parent::rollBack();
    }

    public function sqlCalls(): int
    {
        return $this->sqlCalls;
    }

    public function advisoryCalls(): int
    {
        return $this->advisoryCalls;
    }

    public function transactionCalls(): int
    {
        return $this->transactionCalls;
    }

    public function resetWorkCounters(): void
    {
        $this->sqlCalls = 0;
        $this->advisoryCalls = 0;
        $this->transactionCalls = 0;
    }

    private function countSql(string $sql): void
    {
        ++$this->sqlCalls;
        if (str_contains($sql, 'pg_try_advisory_lock') || str_contains($sql, 'pg_advisory_unlock')) {
            ++$this->advisoryCalls;
        }
    }
}
