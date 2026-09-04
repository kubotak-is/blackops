<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
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
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
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
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Relay\AuditIngressCanonicalizer;
use BlackOps\Internal\Audit\Relay\AuditOutboxRelayRuntime;
use BlackOps\Internal\Audit\Relay\AuditRelayException;
use BlackOps\Internal\Audit\Relay\AuditRelayFailure;
use BlackOps\Internal\Audit\Relay\AuditRelayOutcome;
use BlackOps\Internal\Audit\Relay\AuditRelayPersistence;
use BlackOps\Internal\Audit\Relay\PostgreSqlAuditRelayPersistence;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuditOutboxRelayTest extends TestCase
{
    private const string REAL_SCHEMA = 'blackops_audit_relay_d0_real';

    private const string NOW = '2026-09-04T00:00:00.123456Z';

    private const string RECORD_ID = '01941f29-7c00-7000-8000-000000000501';

    private const string TOKEN = '01941f29-7c00-7000-8000-000000000502';

    private const string SEGMENT_ID = '01941f29-7c00-7000-8000-000000000503';

    private const string TENANT_RECORD_ID = '01941f29-7c00-7000-8000-000000000504';

    private const string TENANT_TOKEN = '01941f29-7c00-7000-8000-000000000505';

    private const string TENANT_SEGMENT_ID = '01941f29-7c00-7000-8000-000000000506';

    private const string SECOND_RECORD_ID = '01941f29-7c00-7000-8000-000000000507';

    private const string SECOND_TOKEN = '01941f29-7c00-7000-8000-000000000508';

    private const string STALE_TOKEN = '01941f29-7c00-7000-8000-000000000509';

    private const string SECOND_SEGMENT_ID = '01941f29-7c00-7000-8000-00000000050a';

    private const string RACE_RECORD_ID = '01941f29-7c00-7000-8000-00000000050b';

    private const string RACE_TOKEN = '01941f29-7c00-7000-8000-00000000050c';

    private const string RACE_SEGMENT_ID = '01941f29-7c00-7000-8000-00000000050d';

    private const string TENANT_RACE_RECORD_ID = '01941f29-7c00-7000-8000-00000000050e';

    private const string TENANT_RACE_TOKEN = '01941f29-7c00-7000-8000-00000000050f';

    private const string SYSTEM_RACE_RECORD_ID = '01941f29-7c00-7000-8000-000000000510';

    private const string SYSTEM_RACE_TOKEN = '01941f29-7c00-7000-8000-000000000511';

    private const string SYSTEM_RACE_SECOND_ID = '01941f29-7c00-7000-8000-000000000512';

    private const string SYSTEM_RACE_SECOND_TOKEN = '01941f29-7c00-7000-8000-000000000513';

    private const string RACE_SYSTEM_SEGMENT_ID = '01941f29-7c00-7000-8000-000000000514';

    private const string RACE_TENANT_SEGMENT_ID = '01941f29-7c00-7000-8000-000000000515';

    public function testRealPostgreSqlSystemRelayCommitsAndPersistsTheThreePhaseResult(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            self::assertNotSame(
                $connection->fetchOne('SELECT pg_backend_pid()'),
                $probe->fetchOne('SELECT pg_backend_pid()'),
            );

            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $configuration = new AuditOutboxConfiguration();
            $bundle = $this->bundle($connection, $schema, $clock, [self::RECORD_ID, self::TOKEN], $configuration);
            $phaseEvents = [];
            $observer = new RelayD1PersistenceObserver(
                $bundle['persistence'],
                function (AuditOutboxLookupResult $result) use (
                    &$phaseEvents,
                    $probe,
                    $outboxSchema,
                    $recordSchema,
                    $connection,
                ): void {
                    $phaseEvents[] = 'claim';
                    self::assertTrue($result->isFound());
                    $value = $result->record()?->toArray();
                    self::assertIsArray($value);
                    self::assertSame('leased', $value['state']);
                    self::assertSame(1, $value['attempt']);
                    self::assertSame(2, $value['version']);
                    self::assertIsString($value['leaseFencingToken']);
                    self::assertNotSame('', $value['leaseFencingToken']);
                    self::assertIsString($value['leaseExpiresAt']);
                    self::assertNotSame('', $value['leaseExpiresAt']);
                    self::assertCount(0, $this->canonicalRows($probe, $recordSchema, 'system:global'));
                    self::assertSame('leased', $this->outboxRow($probe, $outboxSchema, self::RECORD_ID)['state']);
                    $this->assertConnectionIdle($connection);
                },
                function (AuditAppendResult $result) use (
                    &$phaseEvents,
                    $probe,
                    $outboxSchema,
                    $recordSchema,
                    $connection,
                ): void {
                    $phaseEvents[] = 'append';
                    self::assertTrue($result->inserted());
                    $rows = $this->canonicalRows($probe, $recordSchema, 'system:global');
                    self::assertCount(1, $rows);
                    self::assertSame('1', (string) $rows[0]['sequence']);
                    self::assertSame('BOPD', substr($rows[0]['protected_body'], 0, 4));
                    $leased = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
                    self::assertSame('leased', $leased['state']);
                    self::assertSame('2', (string) $leased['version']);
                    self::assertIsString($leased['lease_fencing_token']);
                    self::assertIsString($leased['lease_expires_at']);
                    $this->assertConnectionIdle($connection);
                },
                function (AuditOutboxRecord $result) use (
                    &$phaseEvents,
                    $probe,
                    $outboxSchema,
                    $recordSchema,
                    $connection,
                ): void {
                    $phaseEvents[] = 'ack';
                    $value = $result->toArray();
                    self::assertSame('relayed', $value['state']);
                    self::assertSame(3, $value['version']);
                    self::assertNull($value['leaseFencingToken']);
                    self::assertNull($value['leaseExpiresAt']);
                    $relayed = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
                    self::assertSame('relayed', $relayed['state']);
                    self::assertSame('3', (string) $relayed['version']);
                    self::assertNull($relayed['lease_fencing_token']);
                    self::assertNull($relayed['lease_expires_at']);
                    self::assertCount(1, $this->canonicalRows($probe, $recordSchema, 'system:global'));
                    $this->assertConnectionIdle($connection);
                },
            );
            $runtime = new AuditOutboxRelayRuntime($connection, $observer, $bundle['canonicalizer'], $configuration);
            $seed = $bundle['outbox']->appendDurable($this->draft(), 'relay-d0-system');
            self::assertTrue($seed->inserted());
            $seedValue = $seed->record()->toArray();
            self::assertSame(self::RECORD_ID, $seedValue['recordId']);
            self::assertSame('system:global', $seedValue['partitionId']);
            self::assertSame(1, $seedValue['ingressOrdinal']);
            self::assertSame('pending', $seedValue['state']);
            self::assertSame(0, $seedValue['attempt']);
            self::assertSame(1, $seedValue['version']);
            self::assertNotNull($seedValue['protectedBody']);
            $this->assertConnectionIdle($connection);

            $outcome = $runtime->relayOne();
            self::assertSame(AuditRelayOutcome::RelayedInserted, $outcome);
            self::assertSame(['claim', 'append', 'ack'], $phaseEvents);
            $this->assertConnectionIdle($connection);

            $outboxRow = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('relayed', $outboxRow['state']);
            self::assertSame('1', (string) $outboxRow['attempt']);
            self::assertNull($outboxRow['available_at']);
            self::assertNull($outboxRow['lease_fencing_token']);
            self::assertNull($outboxRow['lease_expires_at']);
            self::assertSame('3', (string) $outboxRow['version']);
            self::assertSame(self::RECORD_ID, $outboxRow['record_id']);
            self::assertSame('system:global', $outboxRow['partition_id']);
            self::assertSame('1', (string) $outboxRow['ingress_ordinal']);
            self::assertSame($seedValue['contentHash'], $outboxRow['content_hash']);
            self::assertSame($seedValue['protectedBody'], $outboxRow['protected_body']);

            $canonicalRows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalRows);
            self::assertSame(self::RECORD_ID, $canonicalRows[0]['record_id']);
            self::assertSame('system:global', $canonicalRows[0]['partition_id']);
            self::assertSame('1', (string) $canonicalRows[0]['sequence']);
            self::assertSame($seedValue['contentHash'], $canonicalRows[0]['content_hash']);
            self::assertSame('BOPD', substr($canonicalRows[0]['protected_body'], 0, 4));
            self::assertGreaterThanOrEqual(53, strlen($canonicalRows[0]['protected_body']));

            $state = $this->canonicalState($probe, $recordSchema, 'system:global');
            self::assertSame('system:global', $state['partition_id']);
            self::assertSame('1', (string) $state['last_sequence']);
            self::assertSame($canonicalRows[0]['record_hash'], $state['head_record_hash']);
            self::assertSame('1', (string) $state['state_version']);

            $outboxState = $this->outboxState($probe, $outboxSchema, 'system:global');
            self::assertSame('1', (string) $outboxState['last_ingress_ordinal']);
            self::assertSame('1', (string) $outboxState['state_version']);
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->getTransactionNestingLevel());
        } finally {
            $this->cleanup($admin, [$connection, $probe], $schema);
        }
    }

    public function testRealPostgreSqlTenantExistingAppendConvergesWithoutDuplicateCanonicalBytes(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $tenant = new AuditTypedReference('account', 'relay-d1-tenant');
        $partition = $this->partition($tenant);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $configuration = new AuditOutboxConfiguration();
            $bundle = $this->bundle(
                $connection,
                $schema,
                $clock,
                [self::TENANT_RECORD_ID, self::TENANT_TOKEN],
                $configuration,
                self::TENANT_SEGMENT_ID,
            );
            $seed = $bundle['outbox']->appendDurable($this->draft($tenant), 'relay-d1-tenant');
            $seedValue = $seed->record()->toArray();
            self::assertSame($partition, $seedValue['partitionId']);
            self::assertSame($tenant->toArray(), $seedValue['tenantBinding']);

            $pending = $bundle['outbox']->find(self::TENANT_RECORD_ID, $tenant, $partition);
            self::assertTrue($pending->isFound());
            $logical = $bundle['canonicalizer']->canonicalize($pending->record());
            $firstAppend = $bundle['records']->appendDurable($logical);
            self::assertTrue($firstAppend->inserted());
            $before = $this->canonicalRows($probe, $recordSchema, $partition);
            self::assertCount(1, $before);
            $beforeBody = $before[0]['protected_body'];
            $beforeHash = $before[0]['record_hash'];

            self::assertSame(AuditRelayOutcome::RelayedExisting, $bundle['runtime']->relayOne());
            $this->assertConnectionIdle($connection);
            $after = $this->canonicalRows($probe, $recordSchema, $partition);
            self::assertCount(1, $after);
            self::assertSame($before[0]['record_id'], $after[0]['record_id']);
            self::assertSame($before[0]['content_hash'], $after[0]['content_hash']);
            self::assertSame($beforeHash, $after[0]['record_hash']);
            self::assertSame($beforeBody, $after[0]['protected_body']);
            self::assertSame('1', (string) $after[0]['sequence']);

            $outbox = $this->outboxRow($probe, $outboxSchema, self::TENANT_RECORD_ID);
            self::assertSame('relayed', $outbox['state']);
            self::assertSame('1', (string) $outbox['attempt']);
            self::assertSame('3', (string) $outbox['version']);
            self::assertSame($partition, $outbox['partition_id']);
            self::assertSame('account', $outbox['tenant_type']);
            self::assertSame('relay-d1-tenant', $outbox['tenant_id']);
            self::assertNull($outbox['lease_fencing_token']);
        } finally {
            $this->cleanup($admin, [$connection, $probe], $schema);
        }
    }

    public function testRealPostgreSqlCrashAfterClaimRecoversPendingAndRelaysInserted(): void
    {
        $this->assertCrashWindow(false);
    }

    public function testRealPostgreSqlCrashAfterAppendRecoversAndRelaysExisting(): void
    {
        $this->assertCrashWindow(true);
    }

    private function assertCrashWindow(bool $appendBeforeCrash): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $recovery = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $configuration = new AuditOutboxConfiguration(leaseDurationSeconds: 1, backoffBaseSeconds: 1);
        $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $bundle = $this->bundle($connection, $schema, $clock, [self::RECORD_ID, self::TOKEN], $configuration);
            $seed = $bundle['outbox']->appendDurable(
                $this->draft(),
                $appendBeforeCrash ? 'relay-d1-append-crash' : 'relay-d1-claim-crash',
            );
            $claimed = $bundle['outbox']->claimNext();
            self::assertTrue($claimed->isFound());
            $claimedValue = $claimed->record()?->toArray();
            self::assertIsArray($claimedValue);
            self::assertSame('leased', $claimedValue['state']);
            self::assertSame(1, $claimedValue['attempt']);
            self::assertSame(2, $claimedValue['version']);
            self::assertIsString($claimedValue['leaseFencingToken']);
            self::assertCount(0, $this->canonicalRows($probe, $recordSchema, 'system:global'));
            $this->assertConnectionIdle($connection);

            $canonicalBeforeCrash = null;
            if ($appendBeforeCrash) {
                $logical = $bundle['canonicalizer']->canonicalize($claimed->record());
                $append = $bundle['records']->appendDurable($logical);
                self::assertTrue($append->inserted());
                $canonicalBeforeCrash = $this->canonicalRows($probe, $recordSchema, 'system:global');
                self::assertCount(1, $canonicalBeforeCrash);
                self::assertSame('1', (string) $canonicalBeforeCrash[0]['sequence']);
                self::assertSame($claimedValue['contentHash'], $canonicalBeforeCrash[0]['content_hash']);
                $this->assertConnectionIdle($connection);
            }

            $connection->close();
            $recovery = $this->realConnection();
            $recoveryBundle = $this->bundle($recovery, $schema, $clock, [self::TOKEN], $configuration);
            $clock->set(new \DateTimeImmutable('2026-09-04T00:00:02.123456Z'));
            self::assertSame(AuditRelayOutcome::RecoveredPending, $recoveryBundle['runtime']->recoverOne());
            $pending = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('pending', $pending['state']);
            self::assertSame('1', (string) $pending['attempt']);
            self::assertSame('3', (string) $pending['version']);
            self::assertNotNull($pending['available_at']);
            self::assertNull($pending['lease_fencing_token']);
            self::assertCount($appendBeforeCrash ? 1 : 0, $this->canonicalRows($probe, $recordSchema, 'system:global'));
            $this->assertConnectionIdle($recovery);

            $clock->set(new \DateTimeImmutable('2026-09-04T00:00:04.123456Z'));
            $expected = $appendBeforeCrash ? AuditRelayOutcome::RelayedExisting : AuditRelayOutcome::RelayedInserted;
            self::assertSame($expected, $recoveryBundle['runtime']->relayOne());
            $this->assertConnectionIdle($recovery);
            $canonicalAfter = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalAfter);
            self::assertSame('1', (string) $canonicalAfter[0]['sequence']);
            self::assertSame($seed->record()->toArray()['recordId'], $canonicalAfter[0]['record_id']);
            self::assertSame($seed->record()->toArray()['contentHash'], $canonicalAfter[0]['content_hash']);
            if ($canonicalBeforeCrash !== null) {
                self::assertSame($canonicalBeforeCrash[0]['record_hash'], $canonicalAfter[0]['record_hash']);
                self::assertSame($canonicalBeforeCrash[0]['protected_body'], $canonicalAfter[0]['protected_body']);
            }
            $relayed = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('relayed', $relayed['state']);
            self::assertSame('5', (string) $relayed['version']);
            self::assertNull($relayed['lease_fencing_token']);
            self::assertNull($relayed['lease_expires_at']);
        } finally {
            $this->cleanup($admin, [$connection, $probe, $recovery], $schema);
        }
    }

    public function testRealPostgreSqlFinalAttemptCrashPoisonsAndReleasesTheNextOrdinal(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $recovery = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $configuration = new AuditOutboxConfiguration(leaseDurationSeconds: 1, maxAttempts: 1, backoffBaseSeconds: 1);
        $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $bundle = $this->bundle(
                $connection,
                $schema,
                $clock,
                [self::RECORD_ID, self::SECOND_RECORD_ID, self::TOKEN],
                $configuration,
            );
            $first = $bundle['outbox']->appendDurable($this->draft(), 'relay-d2-final-first');
            $second = $bundle['outbox']->appendDurable($this->draft(), 'relay-d2-final-second');
            self::assertSame(1, $first->record()->toArray()['ingressOrdinal']);
            self::assertSame(2, $second->record()->toArray()['ingressOrdinal']);

            $claim = $bundle['outbox']->claimNext();
            self::assertTrue($claim->isFound());
            $claimValue = $claim->record()?->toArray();
            self::assertIsArray($claimValue);
            self::assertSame(1, $claimValue['attempt']);
            self::assertSame(2, $claimValue['version']);
            self::assertIsString($claimValue['leaseFencingToken']);
            self::assertCount(0, $this->canonicalRows($probe, $recordSchema, 'system:global'));

            $before = $bundle['canonicalizer']->canonicalize($claim->record());
            self::assertTrue($bundle['records']->appendDurable($before)->inserted());
            $canonicalBefore = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalBefore);
            $this->assertConnectionIdle($connection);

            $secondBeforeTerminal = $this->outboxRow($probe, $outboxSchema, self::SECOND_RECORD_ID);
            self::assertSame('pending', $secondBeforeTerminal['state']);
            $blocked = $bundle['outbox']->claimNext();
            self::assertFalse($blocked->isFound());
            self::assertSame('pending', $this->outboxRow($probe, $outboxSchema, self::SECOND_RECORD_ID)['state']);
            $connection->close();

            $recovery = $this->realConnection();
            $recoveryBundle = $this->bundle($recovery, $schema, $clock, [self::SECOND_TOKEN], $configuration);
            $clock->set(new \DateTimeImmutable('2026-09-04T00:00:02.123456Z'));
            self::assertSame(AuditRelayOutcome::RecoveryPoisoned, $recoveryBundle['runtime']->recoverOne());
            $poisoned = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('poisoned', $poisoned['state']);
            self::assertSame('1', (string) $poisoned['attempt']);
            self::assertSame('3', (string) $poisoned['version']);
            self::assertNull($poisoned['lease_fencing_token']);
            self::assertNull($poisoned['lease_expires_at']);
            $canonicalAfterPoison = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalAfterPoison);
            self::assertSame($canonicalBefore[0]['record_hash'], $canonicalAfterPoison[0]['record_hash']);
            self::assertSame($canonicalBefore[0]['protected_body'], $canonicalAfterPoison[0]['protected_body']);
            $this->assertConnectionIdle($recovery);

            $clock->set(new \DateTimeImmutable('2026-09-04T00:00:04.123456Z'));
            self::assertSame(AuditRelayOutcome::RelayedInserted, $recoveryBundle['runtime']->relayOne());
            $this->assertConnectionIdle($recovery);
            $released = $this->outboxRow($probe, $outboxSchema, self::SECOND_RECORD_ID);
            self::assertSame('relayed', $released['state']);
            self::assertSame('2', (string) $released['ingress_ordinal']);
            self::assertSame('3', (string) $released['version']);
            $canonicalRows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(2, $canonicalRows);
            self::assertSame('1', (string) $canonicalRows[0]['sequence']);
            self::assertSame('2', (string) $canonicalRows[1]['sequence']);
            self::assertSame($canonicalRows[0]['record_hash'], $canonicalRows[1]['previous_record_hash']);
        } finally {
            $this->cleanup($admin, [$connection, $probe, $recovery], $schema);
        }
    }

    public function testRealPostgreSqlAckCommitResponseLossLeavesOneCommittedRowAndLaterIdle(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $configuration = new AuditOutboxConfiguration();
            $bundle = $this->bundle($connection, $schema, $clock, [self::RECORD_ID, self::TOKEN], $configuration);
            $bundle['outbox']->appendDurable($this->draft(), 'relay-d2-response-loss');
            $responseLost = new RelayD2AckResponseLossPersistence($bundle['persistence']);
            $runtime = new AuditOutboxRelayRuntime(
                $connection,
                $responseLost,
                $bundle['canonicalizer'],
                $configuration,
            );
            try {
                $runtime->relayOne();
                self::fail('lost acknowledgement response was accepted');
            } catch (AuditRelayException $exception) {
                self::assertSame(AuditRelayFailure::StorageUnavailable, $exception->failure());
                self::assertSame('storage_unavailable', $exception->code());
                self::assertNull($exception->getPrevious());
            }
            $this->assertConnectionIdle($connection);
            $outbox = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('relayed', $outbox['state']);
            self::assertSame('3', (string) $outbox['version']);
            self::assertNull($outbox['lease_fencing_token']);
            $rows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $rows);
            self::assertSame('1', (string) $rows[0]['sequence']);
            self::assertSame('BOPD', substr($rows[0]['protected_body'], 0, 4));

            self::assertSame(AuditRelayOutcome::Idle, $bundle['runtime']->relayOne());
            self::assertCount(1, $this->canonicalRows($probe, $recordSchema, 'system:global'));
            self::assertSame('relayed', $this->outboxRow($probe, $outboxSchema, self::RECORD_ID)['state']);
        } finally {
            $this->cleanup($admin, [$connection, $probe], $schema);
        }
    }

    public function testRealPostgreSqlStaleTokenAndVersionSettlementsAreTransitionConflicts(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $bundle = $this->bundle($connection, $schema, $clock, [self::RECORD_ID, self::TOKEN]);
            $bundle['outbox']->appendDurable($this->draft(), 'relay-d2-stale');
            $claim = $bundle['outbox']->claimNext();
            self::assertTrue($claim->isFound());
            $before = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            foreach ([
                ['token', 2, self::STALE_TOKEN],
                ['version', 9, self::TOKEN],
            ] as [$kind, $version, $token]) {
                try {
                    $bundle['persistence']->relay(self::RECORD_ID, null, 'system:global', $version, $token);
                    self::fail($kind . ' settlement was accepted');
                } catch (AuditOutboxStoreException $exception) {
                    self::assertSame(AuditOutboxStoreFailure::TransitionConflict, $exception->failure(), $kind);
                    self::assertNull($exception->getPrevious(), $kind);
                }
                self::assertSame($before, $this->outboxRow($probe, $outboxSchema, self::RECORD_ID), $kind);
                self::assertCount(0, $this->canonicalRows($probe, $recordSchema, 'system:global'));
                $this->assertConnectionIdle($connection);
            }
            $settled = $bundle['persistence']->poison(self::RECORD_ID, null, 'system:global', 2, self::TOKEN);
            self::assertSame('poisoned', $settled->toArray()['state']);
            self::assertSame('poisoned', $this->outboxRow($probe, $outboxSchema, self::RECORD_ID)['state']);
        } finally {
            $this->cleanup($admin, [$connection, $probe], $schema);
        }
    }

    public function testRealPostgreSqlTwoFreshConnectionsRaceOneHeadWithoutDuplicateCanonicalData(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the connection race fixture.');
        }

        $admin = null;
        $seedConnection = null;
        $probe = null;
        $lockConnection = null;
        $firstPid = null;
        $secondPid = null;
        $firstStatus = 0;
        $secondStatus = 0;
        $firstFinished = false;
        $secondFinished = false;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $readyOne = tempnam(sys_get_temp_dir(), 'relay-d3-ready-one-');
        $readyTwo = tempnam(sys_get_temp_dir(), 'relay-d3-ready-two-');
        $start = tempnam(sys_get_temp_dir(), 'relay-d3-start-');
        $resultOne = tempnam(sys_get_temp_dir(), 'relay-d3-result-one-');
        $resultTwo = tempnam(sys_get_temp_dir(), 'relay-d3-result-two-');
        foreach ([$readyOne, $readyTwo, $start, $resultOne, $resultTwo] as $path) {
            self::assertIsString($path);
            $this->unlinkSignal($path);
        }
        self::assertIsString($readyOne);
        self::assertIsString($readyTwo);
        self::assertIsString($start);
        self::assertIsString($resultOne);
        self::assertIsString($resultTwo);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $seedConnection = $this->realConnection();
            $probe = $this->realConnection();
            $seedClock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $seedBundle = $this->bundle(
                $seedConnection,
                $schema,
                $seedClock,
                [self::RACE_RECORD_ID],
                segmentId: self::RACE_SEGMENT_ID,
            );
            $seed = $seedBundle['outbox']->appendDurable($this->draft(), 'relay-d3-race');
            self::assertTrue($seed->inserted());
            $seedConnection->close();
            $seedConnection = null;

            $lockConnection = $this->realConnection();
            $lockConnection->beginTransaction();
            $this->lockPartition($lockConnection, $schema, 'system:global');
            $firstPid = $this->forkRelayWorker($readyOne, $start, $resultOne, $schema, self::RACE_TOKEN);
            $secondPid = $this->forkRelayWorker($readyTwo, $start, $resultTwo, $schema, self::RACE_TOKEN);
            $this->waitForSignal($readyOne, 'first race worker readiness');
            $this->waitForSignal($readyTwo, 'second race worker readiness');
            self::assertTrue(file_put_contents($start, 'go') !== false);
            $this->waitForAdvisoryWait($probe, $readyOne);
            $this->waitForAdvisoryWait($probe, $readyTwo);
            $lockConnection->commit();

            $firstFinished = $this->waitForChild($firstPid, $firstStatus);
            $secondFinished = $this->waitForChild($secondPid, $secondStatus);
            self::assertTrue($firstFinished);
            self::assertTrue($secondFinished);
            self::assertTrue(pcntl_wifexited($firstStatus));
            self::assertTrue(pcntl_wifexited($secondStatus));
            self::assertSame(0, pcntl_wexitstatus($firstStatus));
            self::assertSame(0, pcntl_wexitstatus($secondStatus));
            $outcomes = [
                $this->childResult($resultOne)['outcome'],
                $this->childResult($resultTwo)['outcome'],
            ];
            self::assertSame(
                1,
                count(array_filter($outcomes, static fn(mixed $value): bool => $value === 'relayed_inserted')),
            );
            self::assertSame(1, count(array_filter($outcomes, static fn(mixed $value): bool => $value === 'idle')));
            foreach ([$resultOne, $resultTwo] as $resultFile) {
                $payload = $this->childResult($resultFile);
                self::assertTrue($payload['connected']);
                self::assertFalse($payload['active']);
                self::assertSame(0, $payload['level']);
            }

            $outbox = $this->outboxRow($probe, $outboxSchema, self::RACE_RECORD_ID);
            self::assertSame('relayed', $outbox['state']);
            self::assertSame('1', (string) $outbox['attempt']);
            self::assertSame('3', (string) $outbox['version']);
            self::assertNull($outbox['lease_fencing_token']);
            self::assertNull($outbox['lease_expires_at']);
            $rows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $rows);
            self::assertSame('1', (string) $rows[0]['sequence']);
            self::assertSame(self::RACE_RECORD_ID, $rows[0]['record_id']);
            self::assertSame('BOPD', substr($rows[0]['protected_body'], 0, 4));
            $this->assertConnectionIdle($probe);
        } finally {
            if (is_int($firstPid) && !$firstFinished) {
                $this->terminateChild($firstPid, $firstStatus);
            }
            if (is_int($secondPid) && !$secondFinished) {
                $this->terminateChild($secondPid, $secondStatus);
            }
            if ($lockConnection instanceof Connection) {
                try {
                    if ($lockConnection->isTransactionActive()) {
                        $lockConnection->rollBack();
                    }
                } catch (\Throwable) {
                }
            }
            $this->cleanup($admin, [$seedConnection, $probe, $lockConnection], $schema);
            foreach ([$readyOne, $readyTwo, $start, $resultOne, $resultTwo] as $path) {
                if (is_string($path)) {
                    $this->unlinkSignal($path);
                }
            }
        }
    }

    public function testRealPostgreSqlPartitionLockSerializesSamePartitionButAllowsTenantProgress(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the partition serialization fixture.');
        }

        $admin = null;
        $seedConnection = null;
        $probe = null;
        $lockConnection = null;
        $tenantConnection = null;
        $systemAfter = null;
        $systemPid = null;
        $systemStatus = 0;
        $systemFinished = false;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $tenant = new AuditTypedReference('account', 'relay-d3-tenant');
        $tenantPartition = $this->partition($tenant);
        $ready = tempnam(sys_get_temp_dir(), 'relay-d3-serial-ready-');
        $start = tempnam(sys_get_temp_dir(), 'relay-d3-serial-start-');
        $result = tempnam(sys_get_temp_dir(), 'relay-d3-serial-result-');
        foreach ([$ready, $start, $result] as $path) {
            self::assertIsString($path);
            $this->unlinkSignal($path);
        }
        self::assertIsString($ready);
        self::assertIsString($start);
        self::assertIsString($result);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $seedConnection = $this->realConnection();
            $probe = $this->realConnection();
            $seedClock = new RelayD0Clock(new \DateTimeImmutable('2026-09-03T23:59:59.123456Z'));
            $seedBundle = $this->bundle(
                $seedConnection,
                $schema,
                $seedClock,
                [self::SYSTEM_RACE_RECORD_ID, self::SYSTEM_RACE_SECOND_ID, self::TENANT_RACE_RECORD_ID],
                segmentId: self::RACE_SYSTEM_SEGMENT_ID,
            );
            $firstSystem = $seedBundle['outbox']->appendDurable($this->draft(), 'relay-d3-system-first');
            $secondSystem = $seedBundle['outbox']->appendDurable($this->draft(), 'relay-d3-system-second');
            $seedClock->set(new \DateTimeImmutable(self::NOW));
            $tenantSeed = $seedBundle['outbox']->appendDurable($this->draft($tenant), 'relay-d3-tenant');
            self::assertSame(1, $firstSystem->record()->toArray()['ingressOrdinal']);
            self::assertSame(2, $secondSystem->record()->toArray()['ingressOrdinal']);
            self::assertSame(1, $tenantSeed->record()->toArray()['ingressOrdinal']);
            $seedConnection->close();
            $seedConnection = null;

            $lockConnection = $this->realConnection();
            $lockConnection->beginTransaction();
            $this->lockPartition($lockConnection, $schema, 'system:global');
            $systemPid = $this->forkRelayWorker(
                $ready,
                $start,
                $result,
                $schema,
                self::SYSTEM_RACE_TOKEN,
                self::RACE_SYSTEM_SEGMENT_ID,
            );
            $this->waitForSignal($ready, 'system serialization worker readiness');
            self::assertTrue(file_put_contents($start, 'go') !== false);
            $this->waitForAdvisoryWait($probe, $ready);

            $admin->executeStatement(
                'UPDATE '
                . $outboxSchema->recordsTable()
                . ' SET available_at = CAST(:future AS timestamptz) '
                . 'WHERE record_id = CAST(:record_id AS uuid)',
                ['future' => '2026-09-04T00:01:00.123456Z', 'record_id' => self::SYSTEM_RACE_RECORD_ID],
                ['future' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            $tenantConnection = $this->realConnection();
            $tenantClock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $tenantBundle = $this->bundle(
                $tenantConnection,
                $schema,
                $tenantClock,
                [self::TENANT_RACE_TOKEN],
                segmentId: self::RACE_TENANT_SEGMENT_ID,
            );
            self::assertSame(AuditRelayOutcome::RelayedInserted, $tenantBundle['runtime']->relayOne());
            $this->assertConnectionIdle($tenantConnection);
            self::assertSame('relayed', $this->outboxRow($probe, $outboxSchema, self::TENANT_RACE_RECORD_ID)['state']);
            self::assertCount(1, $this->canonicalRows($probe, $recordSchema, $tenantPartition));
            self::assertSame('pending', $this->outboxRow($probe, $outboxSchema, self::SYSTEM_RACE_RECORD_ID)['state']);
            self::assertSame('pending', $this->outboxRow($probe, $outboxSchema, self::SYSTEM_RACE_SECOND_ID)['state']);
            self::assertCount(0, $this->canonicalRows($probe, $recordSchema, 'system:global'));

            $admin->executeStatement(
                'UPDATE '
                . $outboxSchema->recordsTable()
                . ' SET available_at = CAST(:available AS timestamptz) '
                . 'WHERE record_id = CAST(:record_id AS uuid)',
                ['available' => self::NOW, 'record_id' => self::SYSTEM_RACE_RECORD_ID],
                ['available' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            $lockConnection->commit();
            $systemFinished = $this->waitForChild($systemPid, $systemStatus);
            self::assertTrue($systemFinished);
            self::assertTrue(pcntl_wifexited($systemStatus));
            self::assertSame(0, pcntl_wexitstatus($systemStatus));
            $systemPayload = $this->childResult($result);
            self::assertSame('relayed_inserted', $systemPayload['outcome']);
            self::assertTrue($systemPayload['connected']);
            self::assertFalse($systemPayload['active']);
            self::assertSame(0, $systemPayload['level']);

            $systemAfter = $this->realConnection();
            $afterClock = new RelayD0Clock(new \DateTimeImmutable('2026-09-04T00:00:05.123456Z'));
            $afterBundle = $this->bundle(
                $systemAfter,
                $schema,
                $afterClock,
                [self::SYSTEM_RACE_SECOND_TOKEN],
                segmentId: self::RACE_SYSTEM_SEGMENT_ID,
            );
            self::assertSame(AuditRelayOutcome::RelayedInserted, $afterBundle['runtime']->relayOne());
            $this->assertConnectionIdle($systemAfter);
            $systemRows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(2, $systemRows);
            self::assertSame('1', (string) $systemRows[0]['sequence']);
            self::assertSame('2', (string) $systemRows[1]['sequence']);
            self::assertSame($systemRows[0]['record_hash'], $systemRows[1]['previous_record_hash']);
            foreach ([self::SYSTEM_RACE_RECORD_ID, self::SYSTEM_RACE_SECOND_ID] as $recordId) {
                self::assertSame('relayed', $this->outboxRow($probe, $outboxSchema, $recordId)['state']);
            }
            self::assertCount(1, $this->canonicalRows($probe, $recordSchema, $tenantPartition));
        } finally {
            if (is_int($systemPid) && !$systemFinished) {
                $this->terminateChild($systemPid, $systemStatus);
            }
            if ($lockConnection instanceof Connection) {
                try {
                    if ($lockConnection->isTransactionActive()) {
                        $lockConnection->rollBack();
                    }
                } catch (\Throwable) {
                }
            }
            $this->cleanup(
                $admin,
                [$seedConnection, $probe, $lockConnection, $tenantConnection, $systemAfter],
                $schema,
            );
            foreach ([$ready, $start, $result] as $path) {
                if (is_string($path)) {
                    $this->unlinkSignal($path);
                }
            }
        }
    }

    private function forkRelayWorker(
        string $ready,
        string $start,
        string $resultFile,
        PostgreSqlMigrationSchema $schema,
        string $token,
        string $segmentId = self::SEGMENT_ID,
    ): int {
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('unable to fork relay worker');
        }
        if ($pid === 0) {
            $connection = null;
            $payload = ['ok' => false];
            try {
                $connection = $this->realConnection();
                $backendPid = $connection->fetchOne('SELECT pg_backend_pid()');
                if (file_put_contents($ready, (string) $backendPid) === false) {
                    throw new \RuntimeException('relay worker readiness could not be written');
                }
                $this->waitForSignal($start, 'relay worker start');
                $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
                $bundle = $this->bundle($connection, $schema, $clock, [$token], segmentId: $segmentId);
                $outcome = $bundle['runtime']->relayOne();
                $payload = [
                    'ok' => true,
                    'outcome' => $outcome->value,
                    'connected' => $connection->isConnected(),
                    'active' => $connection->isTransactionActive(),
                    'level' => $connection->getTransactionNestingLevel(),
                ];
            } catch (\Throwable $failure) {
                $payload = ['ok' => false, 'error' => get_class($failure)];
            } finally {
                if ($connection instanceof Connection) {
                    try {
                        if ($connection->isTransactionActive()) {
                            $connection->rollBack();
                        }
                    } catch (\Throwable) {
                    }
                    try {
                        $connection->close();
                    } catch (\Throwable) {
                    }
                }
                file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
            }
            exit($payload['ok'] ?? false ? 0 : 1);
        }

        return $pid;
    }

    public function testRealPostgreSqlRecoveryFencesRelayPausedAfterAppend(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the relay recovery fence fixture.');
        }

        $admin = null;
        $seedConnection = null;
        $probe = null;
        $recoveryConnection = null;
        $drainConnection = null;
        $relayPid = null;
        $relayStatus = 0;
        $relayFinished = false;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $configuration = new AuditOutboxConfiguration(leaseDurationSeconds: 1, backoffBaseSeconds: 1);
        $ready = tempnam(sys_get_temp_dir(), 'relay-d3b3-ready-');
        $start = tempnam(sys_get_temp_dir(), 'relay-d3b3-start-');
        $appendCommitted = tempnam(sys_get_temp_dir(), 'relay-d3b3-append-');
        $release = tempnam(sys_get_temp_dir(), 'relay-d3b3-release-');
        $result = tempnam(sys_get_temp_dir(), 'relay-d3b3-result-');
        foreach ([$ready, $start, $appendCommitted, $release, $result] as $path) {
            self::assertIsString($path);
            $this->unlinkSignal($path);
        }
        self::assertIsString($ready);
        self::assertIsString($start);
        self::assertIsString($appendCommitted);
        self::assertIsString($release);
        self::assertIsString($result);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $seedConnection = $this->realConnection();
            $probe = $this->realConnection();
            $seedClock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $seedBundle = $this->bundle($seedConnection, $schema, $seedClock, [self::RECORD_ID], $configuration);
            $seed = $seedBundle['outbox']->appendDurable($this->draft(), 'relay-d3b3-recovery-fence');
            self::assertTrue($seed->inserted());
            $seedValue = $seed->record()->toArray();
            self::assertSame('system:global', $seedValue['partitionId']);
            self::assertSame('pending', $seedValue['state']);
            self::assertSame(1, $seedValue['version']);
            self::assertSame(
                $seedValue['protectedBody'],
                $this->outboxRow($probe, $outboxSchema, self::RECORD_ID)['protected_body'],
            );
            self::assertSame(0, count($this->canonicalRows($probe, $recordSchema, 'system:global')));
            $this->assertConnectionIdle($seedConnection);
            $seedConnection->close();
            $seedConnection = null;
            $seedBundle = null;
            $probe->close();
            $probe = null;
            $admin->close();
            $admin = null;

            $relayPid = $this->forkRelayPauseAfterAppend(
                $ready,
                $start,
                $appendCommitted,
                $release,
                $result,
                $schema,
                self::TOKEN,
            );
            $admin = $this->realConnection();
            $probe = $this->realConnection();
            $this->waitForSignal($ready, 'relay recovery fence worker readiness');
            self::assertTrue(file_put_contents($start, 'go') !== false);
            $this->waitForSignal($appendCommitted, 'relay recovery fence append commit');

            $leased = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('leased', $leased['state']);
            self::assertSame('1', (string) $leased['attempt']);
            self::assertSame('2', (string) $leased['version']);
            self::assertSame(self::TOKEN, $leased['lease_fencing_token']);
            self::assertSame('2026-09-04T00:00:01.123456Z', $leased['lease_expires_at']);
            self::assertNull($leased['available_at']);
            self::assertSame($seedValue['protectedBody'], $leased['protected_body']);
            self::assertSame($seedValue['contentHash'], $leased['content_hash']);
            $canonicalAfterAppend = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalAfterAppend);
            self::assertSame(self::RECORD_ID, $canonicalAfterAppend[0]['record_id']);
            self::assertSame('1', (string) $canonicalAfterAppend[0]['sequence']);
            self::assertSame($seedValue['contentHash'], $canonicalAfterAppend[0]['content_hash']);
            self::assertSame('BOPD', substr($canonicalAfterAppend[0]['protected_body'], 0, 4));
            $canonicalStateAfterAppend = $this->canonicalState($probe, $recordSchema, 'system:global');
            $outboxStateAfterAppend = $this->outboxState($probe, $outboxSchema, 'system:global');
            $this->assertConnectionIdle($probe);

            $recoveryConnection = $this->realConnection();
            $recoveryClock = new RelayD0Clock(new \DateTimeImmutable('2026-09-04T00:00:01.123457Z'));
            $recoveryBundle = $this->bundle(
                $recoveryConnection,
                $schema,
                $recoveryClock,
                [self::SECOND_TOKEN],
                $configuration,
            );
            self::assertSame(AuditRelayOutcome::RecoveredPending, $recoveryBundle['runtime']->recoverOne());
            $this->assertConnectionIdle($recoveryConnection);
            $pending = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('pending', $pending['state']);
            self::assertSame('1', (string) $pending['attempt']);
            self::assertSame('3', (string) $pending['version']);
            self::assertSame('2026-09-04T00:00:02.123457Z', $pending['available_at']);
            self::assertNull($pending['lease_fencing_token']);
            self::assertNull($pending['lease_expires_at']);
            self::assertSame($seedValue['protectedBody'], $pending['protected_body']);
            self::assertSame($seedValue['contentHash'], $pending['content_hash']);
            self::assertSame($canonicalAfterAppend, $this->canonicalRows($probe, $recordSchema, 'system:global'));
            self::assertSame($canonicalStateAfterAppend, $this->canonicalState($probe, $recordSchema, 'system:global'));
            self::assertSame($outboxStateAfterAppend, $this->outboxState($probe, $outboxSchema, 'system:global'));
            $this->assertConnectionIdle($probe);

            self::assertTrue(file_put_contents($release, 'go') !== false);
            $relayFinished = $this->waitForChild($relayPid, $relayStatus);
            self::assertTrue($relayFinished);
            self::assertTrue(pcntl_wifexited($relayStatus));
            self::assertSame(0, pcntl_wexitstatus($relayStatus));
            $relayPayload = $this->childResult($result);
            self::assertSame(AuditRelayOutcome::Stale->value, $relayPayload['outcome']);
            self::assertTrue($relayPayload['connected']);
            self::assertFalse($relayPayload['active']);
            self::assertSame(0, $relayPayload['level']);
            self::assertSame($pending, $this->outboxRow($probe, $outboxSchema, self::RECORD_ID));
            self::assertSame($canonicalAfterAppend, $this->canonicalRows($probe, $recordSchema, 'system:global'));
            self::assertSame($canonicalStateAfterAppend, $this->canonicalState($probe, $recordSchema, 'system:global'));
            self::assertSame($outboxStateAfterAppend, $this->outboxState($probe, $outboxSchema, 'system:global'));
            $this->assertConnectionIdle($probe);

            $drainConnection = $this->realConnection();
            $drainClock = new RelayD0Clock(new \DateTimeImmutable('2026-09-04T00:00:03.123457Z'));
            $drainBundle = $this->bundle($drainConnection, $schema, $drainClock, [self::SECOND_TOKEN], $configuration);
            self::assertSame(AuditRelayOutcome::RelayedExisting, $drainBundle['runtime']->relayOne());
            $this->assertConnectionIdle($drainConnection);
            $relayed = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            self::assertSame('relayed', $relayed['state']);
            self::assertSame('2', (string) $relayed['attempt']);
            self::assertSame('5', (string) $relayed['version']);
            self::assertNull($relayed['available_at']);
            self::assertNull($relayed['lease_fencing_token']);
            self::assertNull($relayed['lease_expires_at']);
            self::assertSame($seedValue['contentHash'], $relayed['content_hash']);
            self::assertSame($seedValue['protectedBody'], $relayed['protected_body']);
            $canonicalFinal = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertSame($canonicalAfterAppend, $canonicalFinal);
            self::assertSame($canonicalAfterAppend[0]['record_hash'], $canonicalFinal[0]['record_hash']);
            self::assertSame($canonicalAfterAppend[0]['protected_body'], $canonicalFinal[0]['protected_body']);
            self::assertSame('1', (string) $canonicalFinal[0]['sequence']);
            self::assertSame($canonicalStateAfterAppend, $this->canonicalState($probe, $recordSchema, 'system:global'));
            self::assertSame($outboxStateAfterAppend, $this->outboxState($probe, $outboxSchema, 'system:global'));
            $this->assertConnectionIdle($probe);
        } finally {
            if (is_int($relayPid) && !$relayFinished) {
                $this->terminateChild($relayPid, $relayStatus);
            }
            $this->cleanup($admin, [$seedConnection, $probe, $recoveryConnection, $drainConnection], $schema);
            foreach ([$ready, $start, $appendCommitted, $release, $result] as $path) {
                if (is_string($path)) {
                    $this->unlinkSignal($path);
                }
            }
        }
    }

    private function forkRelayPauseAfterAppend(
        string $ready,
        string $start,
        string $appendCommitted,
        string $release,
        string $resultFile,
        PostgreSqlMigrationSchema $schema,
        string $token,
    ): int {
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('unable to fork relay recovery fence worker');
        }
        if ($pid === 0) {
            $connection = null;
            $payload = ['ok' => false];
            try {
                $connection = $this->realConnection();
                $backendPid = $connection->fetchOne('SELECT pg_backend_pid()');
                if (file_put_contents($ready, (string) $backendPid) === false) {
                    throw new \RuntimeException('relay recovery fence readiness could not be written');
                }
                $this->waitForSignal($start, 'relay recovery fence worker start');
                $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
                $configuration = new AuditOutboxConfiguration(leaseDurationSeconds: 1, backoffBaseSeconds: 1);
                $bundle = $this->bundle($connection, $schema, $clock, [$token], $configuration);
                $observer = new RelayD1PersistenceObserver(
                    $bundle['persistence'],
                    function (AuditOutboxLookupResult $claimed): void {
                        self::assertTrue($claimed->isFound());
                        $value = $claimed->record()?->toArray();
                        self::assertIsArray($value);
                        self::assertSame('leased', $value['state']);
                        self::assertSame(1, $value['attempt']);
                        self::assertSame(2, $value['version']);
                        self::assertSame(self::TOKEN, $value['leaseFencingToken']);
                    },
                    function (AuditAppendResult $appended) use ($appendCommitted, $release): void {
                        self::assertTrue($appended->inserted());
                        if (file_put_contents($appendCommitted, 'committed') === false) {
                            throw new \RuntimeException('relay recovery fence append signal could not be written');
                        }
                        $this->waitForSignal($release, 'relay recovery fence release');
                    },
                    static function (AuditOutboxRecord $settled): void {
                        throw new \RuntimeException('relay recovery fence settlement unexpectedly succeeded');
                    },
                );
                $runtime = new AuditOutboxRelayRuntime(
                    $connection,
                    $observer,
                    $bundle['canonicalizer'],
                    $configuration,
                );
                $outcome = $runtime->relayOne();
                $payload = [
                    'ok' => true,
                    'outcome' => $outcome->value,
                    'connected' => $connection->isConnected(),
                    'active' => $connection->isTransactionActive(),
                    'level' => $connection->getTransactionNestingLevel(),
                ];
            } catch (\Throwable $failure) {
                $payload = ['ok' => false, 'error' => get_class($failure)];
            } finally {
                if ($connection instanceof Connection) {
                    try {
                        if ($connection->isTransactionActive()) {
                            $connection->rollBack();
                        }
                    } catch (\Throwable) {
                    }
                    try {
                        $connection->close();
                    } catch (\Throwable) {
                    }
                }
                file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
            }
            exit($payload['ok'] ?? false ? 0 : 1);
        }

        return $pid;
    }

    private function lockPartition(Connection $connection, PostgreSqlMigrationSchema $schema, string $partition): void
    {
        $connection->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            [
                'lock_key' => 'blackops.audit.outbox.partition.v1:' . $schema->name() . ':' . $partition,
            ],
            ['lock_key' => ParameterType::STRING],
        );
    }

    private function waitForSignal(string $path, string $description): void
    {
        $deadline = microtime(true) + 5.0;
        while (!is_file($path) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (!is_file($path)) {
            throw new \RuntimeException($description . ' timed out');
        }
    }

    private function waitForAdvisoryWait(Connection $probe, string $ready): void
    {
        $contents = file_get_contents($ready);
        self::assertIsString($contents);
        $pid = (int) trim($contents);
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $wait = $probe->fetchAssociative(
                'SELECT wait_event_type, wait_event FROM pg_stat_activity WHERE pid = :pid',
                ['pid' => $pid],
                ['pid' => ParameterType::INTEGER],
            );
            if (is_array($wait) && $wait['wait_event_type'] === 'Lock' && $wait['wait_event'] === 'advisory') {
                return;
            }
            usleep(10_000);
        }
        self::fail('PostgreSQL advisory lock wait timed out for backend ' . $pid);
    }

    private function waitForChild(int $pid, int &$status): bool
    {
        $deadline = microtime(true) + 5.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid) {
                return true;
            }
            if ($waited === -1) {
                return false;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        $this->terminateChild($pid, $status);

        return false;
    }

    private function terminateChild(int $pid, int &$status): void
    {
        posix_kill($pid, SIGKILL);
        $deadline = microtime(true) + 2.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid || $waited === -1) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
    }

    /** @return array<string,mixed> */
    private function childResult(string $path): array
    {
        $this->waitForSignal($path, 'relay worker result');
        $payload = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false);

        return $payload;
    }

    private function unlinkSignal(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function testRealPostgreSqlAcceptedEvidenceAggregateBoundaryIsExactAndAtomic(): void
    {
        $admin = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $catalog = $this->capacityCatalog();
        $atCapEvidence = $this->capacityEvidence(8192);
        $overCapEvidence = $this->capacityEvidence(8193);
        $jcs = new AuditJcsCodec();
        $capacityTenant = new AuditTypedReference(str_repeat('\\', 256), str_repeat('\\', 256));
        $capacityPartition = $this->partition($capacityTenant);

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $bundle = $this->bundle(
                $connection,
                $schema,
                $clock,
                [self::RECORD_ID, self::SECOND_RECORD_ID],
                catalog: $catalog,
            );
            self::assertCount(32, $catalog->events[0]->evidenceFields);
            self::assertCount(33, $atCapEvidence);
            self::assertCount(33, $overCapEvidence);
            self::assertSame(8192, strlen($jcs->canonicalJson($atCapEvidence)));
            self::assertSame(8193, strlen($jcs->canonicalJson($overCapEvidence)));
            foreach ([$atCapEvidence, $overCapEvidence] as $evidence) {
                foreach ($evidence as $name => $value) {
                    if ($name !== 'version') {
                        self::assertIsString($value);
                        self::assertLessThanOrEqual(256, strlen($value));
                    }
                }
            }

            $seed = $bundle['outbox']->appendDurable(
                $this->capacityDraft($catalog, $atCapEvidence, $capacityTenant),
                'relay-d3b2-at-cap',
            );
            self::assertTrue($seed->inserted());
            $seedValue = $seed->record()->toArray();
            $pending = $bundle['outbox']->find(self::RECORD_ID, $capacityTenant, $capacityPartition);
            self::assertTrue($pending->isFound());
            $expectedLogical = $bundle['canonicalizer']->canonicalize($pending->record());
            $expectedArray = $expectedLogical->toArray();
            self::assertSame($atCapEvidence, $expectedArray['evidence']);
            $expectedLogicalBytes = $bundle['recordsCodec']->logicalBytes($expectedLogical);
            self::assertSame($expectedLogicalBytes, $jcs->canonicalJson($expectedArray));
            self::assertSame($seedValue['contentHash'], $bundle['recordsCodec']->contentHash($expectedLogical));
            self::assertSame(8192, strlen($jcs->canonicalJson($expectedArray['evidence'])));
            self::assertSame(16983, strlen($expectedLogicalBytes));
            $this->assertCapacityLogicalFieldLengths($expectedArray, $catalog);

            self::assertSame(AuditRelayOutcome::RelayedInserted, $bundle['runtime']->relayOne());
            $this->assertConnectionIdle($connection);
            $outbox = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            $canonicalRows = $this->canonicalRows($probe, $recordSchema, $capacityPartition);
            self::assertCount(1, $canonicalRows);
            self::assertSame('relayed', $outbox['state']);
            self::assertSame('3', (string) $outbox['version']);
            self::assertSame($seedValue['contentHash'], $outbox['content_hash']);
            self::assertSame($seedValue['contentHash'], $canonicalRows[0]['content_hash']);
            self::assertSame('1', (string) $canonicalRows[0]['sequence']);
            self::assertLessThanOrEqual(AuditRecordCodec::MAX_BYTES, strlen($outbox['protected_body']));
            self::assertLessThanOrEqual(AuditRecordCodec::MAX_BYTES + 180, strlen($canonicalRows[0]['protected_body']));

            $stored = $this->storedRecord($probe, $recordSchema, self::RECORD_ID);
            $reopened = $bundle['protection']->unprotectRecord($stored, $capacityTenant, $capacityPartition);
            $reopenedArray = $reopened->logicalSnapshot();
            self::assertSame($expectedArray, $reopenedArray);
            self::assertSame($expectedLogicalBytes, $jcs->canonicalJson($reopenedArray));
            self::assertSame($atCapEvidence, $reopenedArray['evidence']);
            self::assertSame(8192, strlen($jcs->canonicalJson($reopenedArray['evidence'])));
            self::assertLessThanOrEqual(AuditRecordCodec::MAX_BYTES, strlen($reopened->bytes()));

            $beforeOutbox = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            $beforeOutboxCount = $probe->fetchOne(
                'SELECT COUNT(*) FROM ' . $outboxSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $capacityPartition],
                ['partition_id' => ParameterType::STRING],
            );
            $beforeOutboxState = $this->outboxState($probe, $outboxSchema, $capacityPartition);
            $beforeCanonicalRows = $this->canonicalRows($probe, $recordSchema, $capacityPartition);
            $beforeCanonicalCount = $probe->fetchOne(
                'SELECT COUNT(*) FROM ' . $recordSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $capacityPartition],
                ['partition_id' => ParameterType::STRING],
            );
            $beforeCanonicalState = $this->canonicalState($probe, $recordSchema, $capacityPartition);

            try {
                $bundle['outbox']->appendDurable(
                    $this->capacityDraft($catalog, $overCapEvidence, $capacityTenant),
                    'relay-d3b2-over-cap',
                );
                self::fail('aggregate cap+1 append unexpectedly succeeded');
            } catch (AuditOutboxStoreException $failure) {
                self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $failure->failure());
            }
            $this->assertConnectionIdle($connection);
            self::assertSame($beforeOutbox, $this->outboxRow($probe, $outboxSchema, self::RECORD_ID));
            self::assertSame($beforeOutboxCount, $probe->fetchOne(
                'SELECT COUNT(*) FROM ' . $outboxSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $capacityPartition],
                ['partition_id' => ParameterType::STRING],
            ));
            self::assertSame($beforeOutboxState, $this->outboxState($probe, $outboxSchema, $capacityPartition));
            self::assertSame($beforeCanonicalRows, $this->canonicalRows($probe, $recordSchema, $capacityPartition));
            self::assertSame($beforeCanonicalCount, $probe->fetchOne(
                'SELECT COUNT(*) FROM ' . $recordSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $capacityPartition],
                ['partition_id' => ParameterType::STRING],
            ));
            self::assertSame($beforeCanonicalState, $this->canonicalState($probe, $recordSchema, $capacityPartition));
        } finally {
            $this->cleanup($admin, [$connection, $probe], $schema);
        }
    }

    public function testRealPostgreSqlRotationKeepsHistoricalOutboxKeySeparateFromRecordKey(): void
    {
        $admin = null;
        $seedConnection = null;
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $recordSchema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $keys = new RelayD3b1RotatingKeySource();

        try {
            $admin = $this->realConnection();
            $this->createRealSchemas($admin, $schema, $outboxSchema, $recordSchema);
            $seedConnection = $this->realConnection();
            $seedClock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $seedBundle = $this->bundle(
                $seedConnection,
                $schema,
                $seedClock,
                [self::RECORD_ID],
                keys: $keys,
                segmentId: self::SEGMENT_ID,
            );
            $seed = $seedBundle['outbox']->appendDurable($this->draft(), 'relay-d3b1-rotation');
            self::assertTrue($seed->inserted());
            $seedValue = $seed->record()->toArray();
            $seedBody = $seedValue['protectedBody'];
            $seedConnection->close();
            $seedConnection = null;
            self::assertSame('relay-d3b1-outbox-old', $keys->activeOutboxId);

            $keys->activeOutboxId = 'relay-d3b1-outbox-new';
            $connection = $this->realConnection();
            $probe = $this->realConnection();
            $clock = new RelayD0Clock(new \DateTimeImmutable(self::NOW));
            $bundle = $this->bundle($connection, $schema, $clock, [self::TOKEN], keys: $keys);
            self::assertSame(AuditRelayOutcome::RelayedInserted, $bundle['runtime']->relayOne());
            $this->assertConnectionIdle($connection);

            $outbox = $this->outboxRow($probe, $outboxSchema, self::RECORD_ID);
            $canonicalRows = $this->canonicalRows($probe, $recordSchema, 'system:global');
            self::assertCount(1, $canonicalRows);
            self::assertSame($seedBody, $outbox['protected_body']);
            self::assertSame($seedBody, $this->outboxRow($probe, $outboxSchema, self::RECORD_ID)['protected_body']);
            self::assertSame('relayed', $outbox['state']);
            self::assertSame('3', (string) $outbox['version']);
            self::assertSame($seedValue['contentHash'], $outbox['content_hash']);
            self::assertSame('1', (string) $canonicalRows[0]['sequence']);
            self::assertSame($seedValue['contentHash'], $canonicalRows[0]['content_hash']);

            $binary = new BopdBinaryEnvelopeCodec();
            $outboxEnvelope = $binary->parse($outbox['protected_body'], AuditRecordCodec::MAX_BYTES);
            $recordEnvelope = $binary->parse($canonicalRows[0]['protected_body'], AuditRecordCodec::MAX_BYTES + 180);
            self::assertSame('relay-d3b1-outbox-old', $outboxEnvelope->keyId());
            self::assertSame('relay-d3b1-record', $recordEnvelope->keyId());
            $keys->activeOutboxId = 'relay-d3b1-outbox-old';
            $oldKey = $keys->activeKey(null, AuditProtectionPurpose::AuditOutboxRecord);
            $keys->activeOutboxId = 'relay-d3b1-outbox-new';
            $newKey = $keys->activeKey(null, AuditProtectionPurpose::AuditOutboxRecord);
            $recordKey = $keys->activeKey(null, AuditProtectionPurpose::AuditRecord);
            self::assertNotSame($oldKey->id(), $newKey->id());
            self::assertNotSame($oldKey->material(), $newKey->material());
            self::assertNotSame($oldKey->material(), $recordKey->material());
            self::assertNotSame($newKey->material(), $recordKey->material());

            $ingress = $this->protectedIngressFromRow($outbox);
            $aad = new AuditAssociatedData();
            $wrongOutboxKey = new RelayD3b1RoleSwapKeySource('outbox')->key(
                $outboxEnvelope->keyId(),
                null,
                AuditProtectionPurpose::AuditOutboxRecord,
            );
            try {
                $binary->open(
                    $outboxEnvelope,
                    $aad->outboxForRead($outboxEnvelope->keyId(), $ingress),
                    $wrongOutboxKey,
                    AuditRecordCodec::MAX_BYTES,
                );
                self::fail('record material opened Outbox ciphertext');
            } catch (\RuntimeException $failure) {
                self::assertSame('Envelope opening failed.', $failure->getMessage());
                $this->assertNoRawKeyMaterial($failure, [
                    $oldKey->material(),
                    $newKey->material(),
                    $recordKey->material(),
                ]);
            }

            $stored = $this->storedRecord($probe, $recordSchema, self::RECORD_ID);
            $wrongRecordKey = new RelayD3b1RoleSwapKeySource('record')->key(
                $recordEnvelope->keyId(),
                null,
                AuditProtectionPurpose::AuditRecord,
            );
            try {
                $binary->open(
                    $recordEnvelope,
                    $aad->recordForRead($recordEnvelope->keyId(), $stored),
                    $wrongRecordKey,
                    AuditRecordCodec::MAX_BYTES,
                );
                self::fail('Outbox material opened canonical ciphertext');
            } catch (\RuntimeException $failure) {
                self::assertSame('Envelope opening failed.', $failure->getMessage());
                $this->assertNoRawKeyMaterial($failure, [
                    $oldKey->material(),
                    $newKey->material(),
                    $recordKey->material(),
                ]);
            }

            $canonicalState = $this->canonicalState($probe, $recordSchema, 'system:global');
            self::assertSame('1', (string) $canonicalState['last_sequence']);
            self::assertSame('1', (string) $canonicalState['state_version']);
            self::assertSame($canonicalRows[0]['record_hash'], $canonicalState['head_record_hash']);
        } finally {
            $this->cleanup($admin, [$seedConnection, $connection, $probe], $schema);
        }
    }

    private function protectedIngressFromRow(array $row): AuditProtectedIngress
    {
        $tenant = $row['tenant_type'] === null ? null : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];

        return AuditProtectedIngress::fromArray([
            'recordId' => $row['record_id'],
            'partitionId' => $row['partition_id'],
            'tenantBinding' => $tenant,
            'ingressOrdinal' => (int) $row['ingress_ordinal'],
            'idempotencyKeyHash' => $row['idempotency_key_hash'],
            'contentHash' => $row['content_hash'],
            'protectedBody' => $row['protected_body'],
        ]);
    }

    private function storedRecord(Connection $probe, PostgreSqlAuditSchema $schema, string $recordId): AuditStoredRecord
    {
        $row = $probe->fetchAssociative(
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'segment_id::text AS segment_id, sequence::text AS sequence, previous_record_hash, content_hash, '
            . 'record_hash, to_char(recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z\') AS recorded_at, '
            . 'event_category, protected_body FROM '
            . $schema->recordsTable()
            . ' WHERE record_id = CAST(:record_id AS uuid)',
            ['record_id' => $recordId],
            ['record_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);
        $tenant = $row['tenant_type'] === null ? null : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];

        return AuditStoredRecord::fromArray([
            'schemaVersion' => (int) $row['schema_version'],
            'kind' => $row['kind'],
            'recordId' => $row['record_id'],
            'partitionId' => $row['partition_id'],
            'tenantBinding' => $tenant,
            'segmentId' => $row['segment_id'],
            'sequence' => (int) $row['sequence'],
            'previousRecordHash' => $row['previous_record_hash'],
            'contentHash' => $row['content_hash'],
            'recordHash' => $row['record_hash'],
            'recordedAt' => $row['recorded_at'],
            'eventCategory' => $row['event_category'],
            'protectedBody' => PostgreSqlBytea::string($row['protected_body']),
        ]);
    }

    /** @param list<string> $materials */
    private function assertNoRawKeyMaterial(\Throwable $failure, array $materials): void
    {
        ob_start();
        var_dump($failure);
        $dump = ob_get_clean();
        self::assertIsString($dump);
        $surfaces = [
            $failure->getMessage(),
            $failure->getTraceAsString(),
            print_r($failure, true),
            var_export($failure, true),
            $this->safeSerialize($failure),
            $dump,
            json_encode($failure, JSON_THROW_ON_ERROR),
        ];
        foreach ($materials as $material) {
            foreach ($surfaces as $surface) {
                self::assertStringNotContainsString($material, $surface);
            }
        }
    }

    private function safeSerialize(\Throwable $failure): string
    {
        try {
            return serialize($failure);
        } catch (\Throwable $serializationFailure) {
            return $serializationFailure->getMessage();
        }
    }

    /** @return array{outbox:PostgreSqlAuditOutboxStore,records:PostgreSqlAuditRecordStore,runtime:AuditOutboxRelayRuntime,canonicalizer:AuditIngressCanonicalizer,persistence:PostgreSqlAuditRelayPersistence,protection:AuditProtectedBodyCodec,recordsCodec:AuditRecordCodec} */
    private function bundle(
        Connection $connection,
        PostgreSqlMigrationSchema $schema,
        RelayD0Clock $clock,
        array $uuids,
        ?AuditOutboxConfiguration $configuration = null,
        string $segmentId = self::SEGMENT_ID,
        ?AuditProtectionKeySource $keys = null,
        ?AuditEventCatalog $catalog = null,
    ): array {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog ?? FrameworkAuditEventCatalog::catalog(),
        ]));
        $recordsCodec = new AuditRecordCodec($validator, $jcs);
        $envelopes = new AuditIngressEnvelopeCodec($recordsCodec, $partitions, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys ?? new RelayD0KeySource(),
            new RelayD0NonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $envelopes,
        );
        $configuration ??= new AuditOutboxConfiguration();
        $outbox = new PostgreSqlAuditOutboxStore(
            $connection,
            new PostgreSqlAuditOutboxSchema($schema),
            $clock,
            new RelayD0UuidGenerator($uuids),
            $validator,
            new AuditRecordFinalizer($validator, $recordsCodec),
            $partitions,
            $envelopes,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator()),
            $configuration,
        );
        $records = new PostgreSqlAuditRecordStore(
            $connection,
            new PostgreSqlAuditSchema($schema->name()),
            $clock,
            new RelayD0UuidGenerator([$segmentId]),
            $validator,
            new AuditRecordFinalizer($validator, $recordsCodec),
            $partitions,
            $recordsCodec,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $canonicalizer = new AuditIngressCanonicalizer($protection, $validator);
        $persistence = new PostgreSqlAuditRelayPersistence($outbox, $records);

        return [
            'outbox' => $outbox,
            'records' => $records,
            'canonicalizer' => $canonicalizer,
            'persistence' => $persistence,
            'protection' => $protection,
            'recordsCodec' => $recordsCodec,
            'runtime' => new AuditOutboxRelayRuntime($connection, $persistence, $canonicalizer, $configuration),
        ];
    }

    private function draft(?AuditTypedReference $tenant = null): AuditRecordDraft
    {
        $definition = $this->durableDefinition();
        $tuple = $definition->semanticTuples[0];

        return new AuditRecordDraft(
            new AuditRecordEvent(
                $definition->type,
                $definition->version,
                $definition->category,
                FrameworkAuditEventCatalog::catalog()->catalogHash,
            ),
            new AuditRecordAction('action-requested', $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', 'relay-d0-actor'),
                new AuditTypedReference('process', 'relay-d0-process'),
            ),
            new AuditRecordResource($tuple->resourceType, 'relay-d0-resource'),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                'relay-d0-policy',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                AuditDelivery::Durable,
            ),
            $tenant,
            '2026-09-04T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, 'relay-d0', AuditSourceRuntime::Cli, 'relay-d0'),
            AuditRecordCorrelation::fromArray([
                'operationId' => null,
                'correlationId' => null,
                'causationId' => null,
                'attempt' => null,
                'traceId' => null,
                'spanId' => null,
                'sampled' => true,
            ]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function capacityCatalog(): AuditEventCatalog
    {
        $fields = [];
        for ($index = 1; $index <= 32; ++$index) {
            $name = 'field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $fields[] = new AuditEvidenceFieldDefinition(
                $name,
                AuditEvidenceType::String,
                true,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            );
        }
        $maximumApplication = 'application.' . str_repeat('a', 116);
        $maximumGrammar = str_repeat('a', 128);
        $tuple = new AuditSemanticTuple(
            $maximumGrammar,
            AuditActionPhase::Committed,
            str_repeat('r', 128),
            AuditOutcomeStatus::Succeeded,
            str_repeat('o', 128),
            AuditPolicyDecision::NotApplicable,
            str_repeat('p', 128),
        );
        $definition = new AuditEventDefinition(
            $maximumApplication,
            AuditValidationException::MAX_SAFE_INTEGER,
            AuditValidationException::MAX_SAFE_INTEGER,
            AuditEventCategory::Configuration,
            [$maximumGrammar],
            [$tuple],
            $fields,
            8192,
            AuditDelivery::Durable,
        );

        return new AuditEventCatalogCodec()->catalog(
            $maximumApplication,
            AuditValidationException::MAX_SAFE_INTEGER,
            AuditSourceOwner::Application,
            [$definition],
        );
    }

    /** @return array<string,mixed> */
    private function capacityEvidence(int $target): array
    {
        $jcs = new AuditJcsCodec();
        $evidence = ['version' => AuditValidationException::MAX_SAFE_INTEGER];
        for ($index = 1; $index <= 32; ++$index) {
            $name = 'field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $evidence[$name] = str_repeat('x', 256);
        }
        self::assertGreaterThan($target, strlen($jcs->canonicalJson($evidence)));
        foreach (array_reverse(array_keys($evidence)) as $name) {
            if ($name === 'version') {
                continue;
            }
            while (strlen($jcs->canonicalJson($evidence)) > $target && strlen((string) $evidence[$name]) > 1) {
                $evidence[$name] = substr((string) $evidence[$name], 0, -1);
            }
        }
        self::assertSame($target, strlen($jcs->canonicalJson($evidence)));

        return $evidence;
    }

    /** @param array<string,mixed> $evidence */
    private function capacityDraft(
        AuditEventCatalog $catalog,
        array $evidence,
        AuditTypedReference $tenant,
    ): AuditRecordDraft {
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $fields = $evidence;
        unset($fields['version']);
        $maximum = str_repeat('\\', 256);
        $traceId = str_repeat('a', 32);
        $spanId = str_repeat('b', 16);

        return new AuditRecordDraft(
            new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalog->catalogHash),
            new AuditRecordAction($maximum, $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference($maximum, $maximum),
                new AuditTypedReference($maximum, $maximum),
            ),
            new AuditRecordResource($tuple->resourceType, $maximum),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                $maximum,
                AuditValidationException::MAX_SAFE_INTEGER,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                $definition->delivery,
            ),
            $tenant,
            '2026-09-04T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Application, $maximum, AuditSourceRuntime::Scheduler, $maximum),
            AuditRecordCorrelation::fromArray([
                'operationId' => $maximum,
                'correlationId' => $maximum,
                'causationId' => $maximum,
                'attempt' => [
                    'number' => AuditValidationException::MAX_SAFE_INTEGER,
                    'kind' => 'initial',
                ],
                'traceId' => $traceId,
                'spanId' => $spanId,
                'sampled' => true,
            ]),
            new AuditRecordEvidence(AuditValidationException::MAX_SAFE_INTEGER, $fields),
        );
    }

    private function assertCapacityLogicalFieldLengths(array $logical, AuditEventCatalog $catalog): void
    {
        $maximumApplication = 'application.' . str_repeat('a', 116);
        $maximumAction = str_repeat('a', 128);
        $maximumResource = str_repeat('r', 128);
        $maximumOutcomeCode = str_repeat('o', 128);
        $maximumReason = str_repeat('p', 128);
        $maximum = str_repeat('\\', 256);
        $definition = $catalog->events[0];
        self::assertSame($maximumApplication, $catalog->catalogId);
        self::assertSame(128, strlen($catalog->catalogId));
        self::assertStringStartsWith('application.', $catalog->catalogId);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $catalog->catalogVersion);
        self::assertSame(AuditSourceOwner::Application, $catalog->owner);
        self::assertSame($maximumApplication, $definition->type);
        self::assertSame(128, strlen($definition->type));
        self::assertStringStartsWith('application.', $definition->type);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $definition->version);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $definition->evidenceVersion);
        self::assertSame(AuditEventCategory::Configuration, $definition->category);
        self::assertSame(AuditDelivery::Durable, $definition->delivery);
        self::assertCount(1, $definition->actionTypes);
        self::assertSame($maximumAction, $definition->actionTypes[0]);
        self::assertSame(128, strlen($definition->actionTypes[0]));
        self::assertCount(1, $definition->semanticTuples);
        $tuple = $definition->semanticTuples[0];
        self::assertSame($maximumAction, $tuple->actionType);
        self::assertSame(128, strlen($tuple->actionType));
        self::assertSame(AuditActionPhase::Committed, $tuple->phase);
        self::assertSame($maximumResource, $tuple->resourceType);
        self::assertSame(128, strlen($tuple->resourceType));
        self::assertSame(AuditOutcomeStatus::Succeeded, $tuple->outcomeStatus);
        self::assertSame($maximumOutcomeCode, $tuple->outcomeCode);
        self::assertSame(128, strlen($tuple->outcomeCode));
        self::assertSame(AuditPolicyDecision::NotApplicable, $tuple->policyDecision);
        self::assertSame($maximumReason, $tuple->policyReasonCode);
        self::assertSame(128, strlen($tuple->policyReasonCode));
        self::assertCount(32, $definition->evidenceFields);
        foreach ($definition->evidenceFields as $index => $field) {
            $name = 'field_' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            self::assertSame($name, $field->name);
            self::assertSame(strlen($name), strlen($field->name));
        }
        self::assertSame(8192, $definition->maxEvidenceBytes);
        $event = $logical['event'];
        $action = $logical['action'];
        $actor = $logical['actor'];
        $resource = $logical['resource'];
        $outcome = $logical['outcome'];
        $policy = $logical['policy'];
        $tenant = $logical['tenant'];
        $source = $logical['source'];
        $correlation = $logical['correlation'];
        self::assertSame($maximumApplication, $event['type']);
        self::assertSame(128, strlen($event['type']));
        self::assertStringStartsWith('application.', $event['type']);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $event['version']);
        self::assertSame(AuditEventCategory::Configuration->value, $event['category']);
        self::assertSame($maximumAction, $action['type']);
        self::assertSame(128, strlen($action['type']));
        self::assertSame($maximum, $action['id']);
        self::assertSame(256, strlen($action['id']));
        self::assertSame($maximum, $actor['principal']['type']);
        self::assertSame($maximum, $actor['principal']['id']);
        self::assertSame($maximum, $actor['execution']['type']);
        self::assertSame($maximum, $actor['execution']['id']);
        self::assertSame(256, strlen($actor['principal']['type']));
        self::assertSame(256, strlen($actor['principal']['id']));
        self::assertSame(256, strlen($actor['execution']['type']));
        self::assertSame(256, strlen($actor['execution']['id']));
        self::assertSame($maximumResource, $resource['type']);
        self::assertSame(128, strlen($resource['type']));
        self::assertSame($maximum, $resource['id']);
        self::assertSame(256, strlen($resource['id']));
        self::assertSame(AuditActionPhase::Committed->value, $action['phase']);
        self::assertSame($outcome['code'], $maximumOutcomeCode);
        self::assertSame(128, strlen($outcome['code']));
        self::assertSame(AuditOutcomeStatus::Succeeded->value, $outcome['status']);
        self::assertSame($policy['reasonCode'], $maximumReason);
        self::assertSame(128, strlen($policy['reasonCode']));
        self::assertSame($maximum, $policy['id']);
        self::assertSame(256, strlen($policy['id']));
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $policy['version']);
        self::assertSame(AuditPolicyDecision::NotApplicable->value, $policy['decision']);
        self::assertSame(AuditDelivery::Durable->value, $policy['auditDelivery']);
        self::assertIsArray($tenant);
        self::assertSame($maximum, $tenant['type']);
        self::assertSame($maximum, $tenant['id']);
        self::assertSame(256, strlen($tenant['type']));
        self::assertSame(256, strlen($tenant['id']));
        self::assertSame($maximum, $source['component']);
        self::assertSame($maximum, $source['buildId']);
        self::assertSame(256, strlen($source['component']));
        self::assertSame(256, strlen($source['buildId']));
        self::assertSame(AuditSourceOwner::Application->value, $source['owner']);
        self::assertSame(AuditSourceRuntime::Scheduler->value, $source['runtime']);
        self::assertSame($maximum, $correlation['operationId']);
        self::assertSame($maximum, $correlation['correlationId']);
        self::assertSame($maximum, $correlation['causationId']);
        self::assertSame(256, strlen($correlation['operationId']));
        self::assertSame(256, strlen($correlation['correlationId']));
        self::assertSame(256, strlen($correlation['causationId']));
        self::assertSame(32, strlen($correlation['traceId']));
        self::assertSame(16, strlen($correlation['spanId']));
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $correlation['attempt']['number']);
        self::assertSame('initial', $correlation['attempt']['kind']);
        self::assertTrue($correlation['sampled'] === true);
        self::assertSame($catalog->events[0]->version, $event['version']);
        self::assertSame($catalog->events[0]->evidenceVersion, $logical['evidence']['version']);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $policy['version']);
        self::assertSame(33, count($logical['evidence']));
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $logical['evidence']['version']);
        for ($index = 1; $index <= 30; ++$index) {
            $name = 'field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            self::assertIsString($logical['evidence'][$name]);
            self::assertSame(str_repeat('x', 256), $logical['evidence'][$name]);
            self::assertSame(256, strlen($logical['evidence'][$name]));
        }
        self::assertIsString($logical['evidence']['field_31']);
        self::assertSame(str_repeat('x', 35), $logical['evidence']['field_31']);
        self::assertSame(35, strlen($logical['evidence']['field_31']));
        self::assertIsString($logical['evidence']['field_32']);
        self::assertSame('x', $logical['evidence']['field_32']);
        self::assertSame(1, strlen($logical['evidence']['field_32']));
    }

    private function durableDefinition(): AuditEventDefinition
    {
        foreach (FrameworkAuditEventCatalog::catalog()->events as $definition) {
            if ($definition->delivery === AuditDelivery::Durable) {
                return $definition;
            }
        }

        throw new \RuntimeException('durable fixture definition is unavailable');
    }

    private function partition(?AuditTypedReference $tenant): string
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenant);
    }

    private function realConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }

    private function createRealSchemas(
        Connection $admin,
        PostgreSqlMigrationSchema $schema,
        PostgreSqlAuditOutboxSchema $outboxSchema,
        PostgreSqlAuditSchema $recordSchema,
    ): void {
        $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $schema->quoted() . ' CASCADE');
        $admin->executeStatement('CREATE SCHEMA ' . $schema->quoted());
        foreach ($outboxSchema->statements() as $statement) {
            $admin->executeStatement($statement);
        }
        foreach ($recordSchema->statements() as $statement) {
            $admin->executeStatement($statement);
        }
    }

    /** @return array<string,mixed> */
    private function outboxRow(Connection $probe, PostgreSqlAuditOutboxSchema $schema, string $recordId): array
    {
        $row = $probe->fetchAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, ingress_ordinal::text AS ingress_ordinal, '
            . 'idempotency_key_hash, content_hash, state, attempt::text AS attempt, '
            . 'CASE WHEN available_at IS NULL THEN NULL ELSE to_char(available_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') END AS available_at, '
            . 'lease_fencing_token::text AS lease_fencing_token, '
            . 'CASE WHEN lease_expires_at IS NULL THEN NULL ELSE to_char(lease_expires_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') END AS lease_expires_at, '
            . 'protected_body, version::text AS version FROM '
            . $schema->recordsTable()
            . ' WHERE record_id = CAST(:record_id AS uuid)',
            ['record_id' => $recordId],
            ['record_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);
        if (is_resource($row['protected_body'] ?? null)) {
            $body = stream_get_contents($row['protected_body']);
            self::assertIsString($body);
            $row['protected_body'] = $body;
        } else {
            $row['protected_body'] = PostgreSqlBytea::string($row['protected_body']);
        }

        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function canonicalRows(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        $rows = $probe->fetchAllAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, sequence::text AS sequence, '
            . 'previous_record_hash, content_hash, record_hash, protected_body FROM '
            . $schema->recordsTable()
            . ' WHERE partition_id = :partition_id ORDER BY sequence ASC',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($rows);
        foreach ($rows as &$row) {
            $row['protected_body'] = PostgreSqlBytea::string($row['protected_body']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,mixed> */
    private function outboxState(Connection $probe, PostgreSqlAuditOutboxSchema $schema, string $partition): array
    {
        $row = $probe->fetchAssociative(
            'SELECT partition_id, last_ingress_ordinal::text AS last_ingress_ordinal, state_version::text AS state_version '
            . 'FROM '
            . $schema->partitionStateTable()
            . ' WHERE partition_id = :partition_id',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string,mixed> */
    private function canonicalState(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        $row = $probe->fetchAssociative(
            'SELECT partition_id, last_sequence::text AS last_sequence, head_record_hash, state_version::text AS state_version '
            . 'FROM '
            . $schema->partitionStateTable()
            . ' WHERE partition_id = :partition_id',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);

        return $row;
    }

    private function assertConnectionIdle(Connection $connection): void
    {
        self::assertTrue($connection->isAutoCommit());
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, $connection->getTransactionNestingLevel());
    }

    /** @param list<?Connection> $connections */
    private function cleanup(?Connection $admin, array $connections, PostgreSqlMigrationSchema $schema): void
    {
        foreach ($connections as $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            try {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (\Throwable) {
            }
            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }
        if ($admin instanceof Connection) {
            try {
                $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $schema->quoted() . ' CASCADE');
            } catch (\Throwable) {
            }
            try {
                $admin->close();
            } catch (\Throwable) {
            }
        }
    }
}

final class RelayD0Clock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $value,
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function set(\DateTimeImmutable $value): void
    {
        $this->value = $value;
    }
}

final class RelayD0UuidGenerator implements Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(\DateTimeImmutable $time): string
    {
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('relay d0 UUID sequence exhausted');
        }

        return $id;
    }
}

final class RelayD0NonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat('d', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    }
}

final class RelayD0KeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey(
            $this->keyId($purpose),
            str_repeat('d', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES),
        );
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat('d', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    private function keyId(AuditProtectionPurpose $purpose): string
    {
        return $purpose === AuditProtectionPurpose::AuditOutboxRecord ? 'relay-d0-outbox' : 'relay-d0-record';
    }
}

final class RelayD1PersistenceObserver implements AuditRelayPersistence
{
    public function __construct(
        private AuditRelayPersistence $delegate,
        private \Closure $afterClaim,
        private \Closure $afterAppend,
        private \Closure $afterRelay,
    ) {}

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        return $this->delegate->recoverNextExpired();
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        $result = $this->delegate->claimNext();
        ($this->afterClaim)($result);

        return $result;
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        $result = $this->delegate->appendCanonical($logical);
        ($this->afterAppend)($result);

        return $result;
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->delegate->retry(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $result = $this->delegate->relay(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
        ($this->afterRelay)($result);

        return $result;
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->delegate->poison(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
    }
}

final class RelayD2AckResponseLossPersistence implements AuditRelayPersistence
{
    public function __construct(
        private AuditRelayPersistence $delegate,
    ) {}

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        return $this->delegate->recoverNextExpired();
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        return $this->delegate->claimNext();
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        return $this->delegate->appendCanonical($logical);
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->delegate->retry(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $result = $this->delegate->relay(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
        throw new \RuntimeException('relay response lost canary');
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->delegate->poison(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
    }
}

final class RelayD3b1RotatingKeySource implements AuditProtectionKeySource
{
    public string $activeOutboxId = 'relay-d3b1-outbox-old';

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        if ($purpose === AuditProtectionPurpose::AuditOutboxRecord) {
            return new StorageKey($this->activeOutboxId, $this->outboxMaterial($this->activeOutboxId));
        }

        return new StorageKey('relay-d3b1-record', str_repeat('r', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        if ($purpose === AuditProtectionPurpose::AuditOutboxRecord) {
            return new StorageKey($keyId, $this->outboxMaterial($keyId));
        }
        if ($keyId !== 'relay-d3b1-record') {
            throw new \LogicException('unknown record key identifier');
        }

        return new StorageKey($keyId, str_repeat('r', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    private function outboxMaterial(string $keyId): string
    {
        return match ($keyId) {
            'relay-d3b1-outbox-old' => str_repeat('o', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES),
            'relay-d3b1-outbox-new' => str_repeat('n', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES),
            default => throw new \LogicException('unknown outbox key identifier'),
        };
    }
}

final class RelayD3b1RoleSwapKeySource implements AuditProtectionKeySource
{
    public function __construct(
        private string $mode,
    ) {}

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        throw new \LogicException('role substitution does not provide an active key');
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        if ($this->mode === 'outbox' && $purpose === AuditProtectionPurpose::AuditOutboxRecord) {
            return new StorageKey($keyId, str_repeat('r', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        }
        if ($this->mode === 'record' && $purpose === AuditProtectionPurpose::AuditRecord) {
            return new StorageKey($keyId, str_repeat('o', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        }

        throw new \LogicException('unexpected role substitution');
    }
}
