<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator as AuditUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\Status\AuditedOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusJournalValidator;
use BlackOps\Internal\Status\OperationStatusReadExecutionSource;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusUnavailable;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;

/** @mago-expect lint:too-many-methods */
/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
final class PostgreSqlAuditedOperationStatusQueryTest extends TestCase
{
    private const string OPERATION_TYPE = 'status.audited.postgresql';

    private const string SCHEMA_PREFIX = 'blackops_p23_d2s2_';

    private const string AUDIT_KEY = '0123456789abcdef0123456789abcdef';

    private string $schema;

    private Connection $admin;

    private Connection $probe;

    private PostgreSqlAuditedStatusConnection $connection;

    private PostgreSqlDeferredOperationSender $sender;

    private PostgreSqlCanonicalJournalStore $journal;

    private PostgreSqlOutcomeStore $outcomes;

    private OperationRegistry $registry;

    private PostgreSqlAuditedStatusKeyProvider $statusKeys;

    private OperationReadAuditSessionFactory $sessions;

    private PostgreSqlAuditedAuditDraftWriter $auditWriter;

    private HmacAuditOpaqueIdDeriver $opaqueIds;

    protected function setUp(): void
    {
        $this->schema = self::SCHEMA_PREFIX . bin2hex(random_bytes(6));
        $this->admin = $this->connection();
        $this->probe = $this->connection();
        $base = $this->connection();
        $params = $base->getParams();
        $driver = $base->getDriver();
        $base->close();
        $this->connection = new PostgreSqlAuditedStatusConnection($params, $driver, $this->schema, $this->probe);
        $this->admin->executeStatement('CREATE SCHEMA "' . $this->schema . '"');

        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            $this->schema,
        );
        $this->sender->migrate();
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            $this->schema,
        );
        $this->journal->migrate();
        $this->outcomes = new PostgreSqlOutcomeStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            $this->schema,
        );
        $this->outcomes->migrate();

        $recordSchema = new PostgreSqlAuditSchema($this->schema);
        $outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($this->schema));
        foreach ([...$recordSchema->statements(), ...$outboxSchema->statements()] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->registry = new OperationRegistry([
            new OperationMetadata(
                self::OPERATION_TYPE,
                PostgreSqlAuditedOperation::class,
                PostgreSqlAuditedValue::class,
                PostgreSqlAuditedOperation::class,
                PostgreSqlAuditedOutcome::class,
                Deferred::class,
            ),
        ]);
        $this->statusKeys = new PostgreSqlAuditedStatusKeyProvider();
        $this->opaqueIds = new HmacAuditOpaqueIdDeriver(self::AUDIT_KEY);
        $this->sessions = $this->auditSessions($recordSchema, $outboxSchema);
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection->isTransactionActive()) {
                $level = $this->connection->getTransactionNestingLevel();
                $this->connection->rollBack();
                if (!$this->connection->isAutoCommit() && $level === 1) {
                    $this->connection->setAutoCommit(true);
                    break;
                }
            }
            if (!$this->connection->isAutoCommit()) {
                $this->connection->setAutoCommit(true);
            }
        } catch (Throwable) {
        }
        try {
            $this->admin->executeStatement('DROP SCHEMA IF EXISTS "' . $this->schema . '" CASCADE');
        } finally {
            $this->admin->close();
            $this->connection->close();
            $this->probe->close();
        }
    }

    public function testRequestedIsVisibleBeforeScopedLookupAndTerminalFollowsReadOnlySnapshot(): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(1);
        $this->createCompleted($operationId, $tenant, 'ready-private');
        $authorizer =
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow(), function (OperationStatusAuthorizationRequest $request) use (
                $operationId,
                $tenant,
            ): void {
                self::assertSame($operationId->toString(), $request->operationId()->toString());
                self::assertCount(1, $this->auditRows($operationId, $tenant));
            });

        $result = $this->query($authorizer)->findForTenant($operationId, new ActorRef('reader-a', 'user'), $tenant);

        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertSame('ready-private', $result->status()->outcome()?->private);
        self::assertSame(1, $authorizer->calls);
        self::assertNotEmpty($this->connection->requestedVisibleBeforeSubject);
        self::assertSame(1, $this->connection->requestedVisibleBeforeSubject[0]);
        self::assertSame(['off', 'on', 'off'], $this->connection->commitModes);
        self::assertSame(1, $this->connection->readOnlyCommits);
        self::assertContains('repeatable read', $this->connection->transactionIsolations);
        self::assertContains('on', $this->connection->transactionReadOnlyStates);
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->assertAuditDraftsContainNoDecodedStatus();

        $rows = $this->auditRows($operationId, $tenant);
        self::assertSame(
            ['access.operation_status.requested', 'access.operation_status.accessed'],
            array_column($rows, 'action_type'),
        );
        $this->assertProtectedRowsContainNoStatusPayload($rows);
        self::assertSame(0, $this->outboxCount());
    }

    public function testExpiredResultPublishesAccessedBeforeReturning410(): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(50);
        $this->createCompleted($operationId, $tenant, 'expired-private');
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."outcomes" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->probe->executeStatement('INSERT INTO "' . $this->schema . '"."retention_purge_audits" (
                audit_id, operation_id, target, affected_count, policy, purged_at, purged_by, tenant_type, tenant_id
             ) VALUES (
                :audit_id, :operation_id, :target, 1, :policy, :purged_at, :purged_by, :tenant_type, :tenant_id
             )', [
            'audit_id' => '019f80ab-6000-7000-8000-000000000050',
            'operation_id' => $operationId->toString(),
            'target' => RetentionPurgeTarget::Outcome->value,
            'policy' => 'status-test-policy',
            'purged_at' => '2026-09-11T00:01:00Z',
            'purged_by' => 'status-test',
            'tenant_type' => $tenant->type(),
            'tenant_id' => $tenant->id(),
        ]);
        $authorizer =
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow(), function () use (
                $operationId,
                $tenant,
            ): void {
                self::assertCount(1, $this->auditRows($operationId, $tenant));
            });

        $result = $this->query($authorizer)->findForTenant($operationId, null, $tenant);

        self::assertInstanceOf(OperationStatusExpired::class, $result);
        self::assertSame(1, $authorizer->calls);
        self::assertGreaterThan(0, $this->statusKeys->calls);
        self::assertSame(2, $this->connection->subjectReadAttempts);
        $this->assertPhases($operationId, $tenant, ['requested', 'accessed']);
        $this->assertAuditDraftsContainNoDecodedStatus();
        self::assertSame(0, $this->outboxCount());
    }

    public function testRequestedAuditAppendFailureLeavesReaderAndTerminalUntouched(): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(51);
        $this->connection->auditAppendFailure = 'requested';
        $authorizer = new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow());

        try {
            $this->query($authorizer)->findForTenant($operationId, null, $tenant);
            self::fail('Requested append failure was swallowed.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $this->connection->auditAppendAttempts);
        self::assertSame(0, $this->connection->commitAttempts);
        self::assertSame(1, $this->connection->rollbackAttempts);
        self::assertSame(0, $this->connection->subjectReadAttempts);
        self::assertSame(0, $authorizer->calls);
        self::assertSame(0, $this->statusKeys->calls);
        self::assertSame([], $this->auditWriter->terminals);
        self::assertSame([], $this->auditRows($operationId, $tenant));
        self::assertSame(0, $this->outboxCount());
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->assertAuditDraftsContainNoDecodedStatus();
    }

    public function testTerminalAuditAppendFailureLeavesOnlyRequestedPrefix(): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(52);
        $this->createCompleted($operationId, $tenant, 'append-failure-private');
        $this->connection->auditAppendFailure = 'terminal';

        try {
            $this->query(
                new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->findForTenant($operationId, null, $tenant);
            self::fail('Terminal append failure was swallowed.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(2, $this->connection->auditAppendAttempts);
        self::assertSame(2, $this->connection->commitAttempts);
        self::assertSame(1, $this->connection->rollbackAttempts);
        self::assertSame(2, $this->connection->subjectReadAttempts);
        self::assertSame(0, $this->outboxCount());
        $this->assertPhases($operationId, $tenant, ['requested']);
        self::assertSame(1, count($this->auditWriter->terminals));
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->assertAuditDraftsContainNoDecodedStatus();
    }

    public function testIdleGuardRejectsAmbientAndAutoCommitFalseWithoutTouchingForeignTransaction(): void
    {
        $this->connection->beginTransaction();
        $commitBefore = $this->connection->commitAttempts;
        $rollbackBefore = $this->connection->rollbackAttempts;

        try {
            $this->query(
                new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->findForTenant($this->id(53), null, $this->tenant('tenant-a'));
            self::fail('Ambient transaction was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame($commitBefore, $this->connection->commitAttempts);
        self::assertSame($rollbackBefore, $this->connection->rollbackAttempts);
        self::assertTrue($this->connection->isTransactionActive());
        self::assertSame(1, $this->connection->getTransactionNestingLevel());
        self::assertSame(0, $this->connection->subjectReadAttempts);
        self::assertSame([], $this->auditWriter->requested);
        self::assertSame([], $this->auditWriter->terminals);
        $this->connection->rollBack();
        self::assertFalse($this->connection->isTransactionActive());

        $this->connection->setAutoCommit(false);
        $commitBefore = $this->connection->commitAttempts;
        $rollbackBefore = $this->connection->rollbackAttempts;
        try {
            $this->query(
                new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->findForTenant($this->id(54), null, $this->tenant('tenant-a'));
            self::fail('Auto-commit false was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($commitBefore, $this->connection->commitAttempts);
        self::assertSame($rollbackBefore, $this->connection->rollbackAttempts);
        self::assertFalse($this->connection->isAutoCommit());
        self::assertFalse($this->connection->isTransactionActive());
        $this->connection->setAutoCommit(true);
        self::assertTrue($this->connection->isAutoCommit());
    }

    public function testScopedPostgresIsolationCoversGlobalNamedForeignDeniedAndMissingRows(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $namedId = $this->id(10);
        $globalId = $this->id(11);
        $deniedId = $this->id(12);
        $foreignId = $this->id(14);
        $this->createCompleted($namedId, $tenantA, 'named-private');
        $this->createCompleted($globalId, null, 'global-private');
        $this->createCompleted($deniedId, $tenantA, 'denied-private');
        $this->createCompleted($foreignId, $tenantB, 'foreign-private');

        $allow = new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow());
        $query = $this->query($allow);
        $sameTenant = $query->findForTenant($namedId, null, $tenantA);
        self::assertInstanceOf(OperationStatusFound::class, $sameTenant);
        self::assertSame('named-private', $sameTenant->status()->outcome()?->private);
        self::assertGreaterThan(0, $this->statusKeys->calls);
        $this->assertPhases($namedId, $tenantA, ['requested', 'accessed']);

        $this->statusKeys->reset();
        $beforeGlobalScopeAuthorizerCalls = $allow->calls;
        $globalScopeNamed = $query->findForTenant($namedId, null, null);
        self::assertInstanceOf(OperationStatusUnavailable::class, $globalScopeNamed);
        self::assertSame($beforeGlobalScopeAuthorizerCalls, $allow->calls);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($namedId, null, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $differentType = new TenantRef('account', 'tenant-a');
        $beforeDifferentTypeAuthorizerCalls = $allow->calls;
        $typeMismatch = $query->findForTenant($namedId, null, $differentType);
        self::assertInstanceOf(OperationStatusUnavailable::class, $typeMismatch);
        self::assertSame($beforeDifferentTypeAuthorizerCalls, $allow->calls);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($namedId, $differentType, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $beforeForeignAuthorizerCalls = $allow->calls;
        $foreign = $query->findForTenant($namedId, null, $tenantB);
        self::assertInstanceOf(OperationStatusUnavailable::class, $foreign);
        self::assertSame($beforeForeignAuthorizerCalls, $allow->calls);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($namedId, $tenantB, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $again = $query->findForTenant($namedId, null, $tenantA);
        self::assertInstanceOf(OperationStatusFound::class, $again);
        self::assertSame('named-private', $again->status()->outcome()?->private);
        self::assertGreaterThan(0, $this->statusKeys->calls);

        $this->statusKeys->reset();
        $global = $query->find($globalId);
        self::assertInstanceOf(OperationStatusFound::class, $global);
        self::assertSame('global-private', $global->status()->outcome()?->private);
        self::assertGreaterThan(0, $this->statusKeys->calls);
        $this->assertPhases($globalId, null, ['requested', 'accessed']);

        $this->statusKeys->reset();
        $beforeGlobalMismatchAuthorizerCalls = $allow->calls;
        $globalFromTenant = $query->findForTenant($globalId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $globalFromTenant);
        self::assertSame($beforeGlobalMismatchAuthorizerCalls, $allow->calls);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($globalId, $tenantA, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $deny = new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::deny());
        $denied = $this->query($deny)->findForTenant($deniedId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $denied);
        self::assertSame(1, $deny->calls);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($deniedId, $tenantA, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $missingId = $this->id(13);
        $missing = $this->query(
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
        )->findForTenant($missingId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $missing);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($missingId, $tenantA, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $foreignCiphertext = $this->query(
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
        )->findForTenant($foreignId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $foreignCiphertext);
        self::assertSame(0, $this->statusKeys->calls);
        $this->assertPhases($foreignId, $tenantA, ['requested', 'unavailable']);

        foreach ([$namedId, $globalId, $deniedId, $foreignId] as $id) {
            $this->assertStoredStatusPayloadIsEncrypted($id);
        }
        $this->assertAuditDraftsContainNoDecodedStatus();
        self::assertSame(0, $this->outboxCount());
    }

    public function testMoveAndDisappearanceAfterAuthorizationBecomeUnavailableWithoutDecode(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $movedId = $this->id(20);
        $this->createCompleted($movedId, $tenantA, 'moved-private');
        $moveAuthorizer =
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow(), function () use (
                $movedId,
                $tenantB,
            ): void {
                $this->moveSubject($movedId, $tenantB);
            });

        $moved = $this->query($moveAuthorizer)->findForTenant($movedId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $moved);
        self::assertSame(1, $moveAuthorizer->calls);
        self::assertSame(0, $this->statusKeys->calls);
        self::assertFalse($this->connection->isTransactionActive());
        $this->assertPhases($movedId, $tenantA, ['requested', 'unavailable']);

        $this->statusKeys->reset();
        $removedId = $this->id(21);
        $this->createCompleted($removedId, $tenantA, 'removed-private');
        $removeAuthorizer =
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow(), function () use (
                $removedId,
            ): void {
                $this->removeSubject($removedId);
            });

        $removed = $this->query($removeAuthorizer)->findForTenant($removedId, null, $tenantA);
        self::assertInstanceOf(OperationStatusUnavailable::class, $removed);
        self::assertSame(1, $removeAuthorizer->calls);
        self::assertSame(0, $this->statusKeys->calls);
        self::assertFalse($this->connection->isTransactionActive());
        $this->assertPhases($removedId, $tenantA, ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoDecodedStatus();
        self::assertSame(0, $this->outboxCount());
    }

    public function testSameScopeAuthorizationIdentityDriftIsIntegrityBeforeDecodeAndRethrow(): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(30);
        $this->createCompleted($operationId, $tenant, 'drift-private');
        $authorizer =
            new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow(), function () use (
                $operationId,
            ): void {
                $this->driftOriginActor($operationId, new ActorRef('origin-drifted', 'customer'));
            });

        try {
            $this->query($authorizer)->findForTenant($operationId, null, $tenant);
            self::fail('Identity drift was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $authorizer->calls);
        self::assertSame(0, $this->statusKeys->calls);
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        $rows = $this->auditRows($operationId, $tenant);
        self::assertSame(
            ['access.operation_status.requested', 'access.operation_status.unavailable'],
            array_column($rows, 'action_type'),
        );
        $this->assertProtectedRowsContainNoStatusPayload($rows);
        $this->assertAuditDraftsContainNoDecodedStatus();
    }

    /** @return iterable<string, array{string}> */
    public static function terminalFailureModes(): iterable
    {
        yield 'terminal commit fails before database commit' => ['before'];
        yield 'terminal commit throws after database commit' => ['after'];
        yield 'terminal poststate is invalid after database commit' => ['poststate'];
    }

    #[DataProvider('terminalFailureModes')]
    public function testTerminalFailureDoesNotRetryAndPreservesCommittedPrefix(string $mode): void
    {
        $tenant = $this->tenant('tenant-a');
        $operationId = $this->id(40);
        $this->createCompleted($operationId, $tenant, 'terminal-private');
        $this->connection->terminalCommitFailure = $mode;

        try {
            $this->query(
                new PostgreSqlAuditedStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->findForTenant($operationId, null, $tenant);
            self::fail('Terminal failure was swallowed.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        $expected = $mode === 'before'
            ? ['access.operation_status.requested']
            : ['access.operation_status.requested', 'access.operation_status.accessed'];
        $rows = $this->auditRows($operationId, $tenant);
        self::assertSame($expected, array_column($rows, 'action_type'));
        self::assertSame(3, $this->connection->commitAttempts);
        self::assertSame(0, $this->outboxCount());
        $this->assertProtectedRowsContainNoStatusPayload($rows);
        $this->assertAuditDraftsContainNoDecodedStatus();
    }

    private function auditSessions(
        PostgreSqlAuditSchema $recordSchema,
        PostgreSqlAuditOutboxSchema $outboxSchema,
    ): OperationReadAuditSessionFactory {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $records);
        $ingress = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new PostgreSqlAuditedAuditKeySource(),
            new PostgreSqlAuditedAuditNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new PostgreSqlAuditedAuditClock();
        $uuids = new PostgreSqlAuditedAuditUuidGenerator();
        $recordStore = new PostgreSqlAuditRecordStore(
            $this->connection,
            $recordSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $this->connection,
            $outboxSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(
                new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator(),
            ),
            new AuditOutboxConfiguration(),
        );
        $delivery = new AuditDeliveryCoordinator(
            $this->connection,
            new AuditDeliverySelector($lookup, $validator),
            $recordStore,
            $outboxStore,
        );
        $this->auditWriter = new PostgreSqlAuditedAuditDraftWriter(new AuditProtectedReadGate($delivery));

        return new OperationReadAuditSessionFactory(
            $this->auditWriter,
            new HmacAuditOpaqueIdDeriver(self::AUDIT_KEY),
            new PostgreSqlAuditedReadNonceSource(),
            new PostgreSqlAuditedReadClock(),
            new OperationStatusReadExecutionSource(new PostgreSqlAuditedExecutionUuidGenerator()),
            AuditSourceRuntime::Http,
            'postgresql-audited-status-test',
        );
    }

    private function query(PostgreSqlAuditedStatusAuthorizer $authorizer): AuditedOperationStatusQuery
    {
        return new AuditedOperationStatusQuery(
            new PostgreSqlOperationStatusSource(
                $this->connection,
                $this->registry,
                new BopdEnvelopeCodec($this->statusKeys),
                $this->schema,
                new OperationStatusJournalValidator(),
            ),
            $authorizer,
            $this->sessions,
            $this->connection,
        );
    }

    private function createCompleted(OperationId $operationId, ?TenantRef $tenant, string $private): void
    {
        $this->sender->enqueue(
            new \BlackOps\Core\Execution\DeferredOperationMessage(
                $operationId,
                self::OPERATION_TYPE,
                1,
                '{"private":"payload"}',
                '{"private":"context"}',
                new DateTimeImmutable('2026-09-11T00:00:00Z'),
                tenant: $tenant,
                originActor: new ActorRef('origin-a', 'customer'),
            ),
        );
        $attempt = new JournalAttempt(
            AttemptId::fromString('019f80ab-8000-7000-8000-' . substr($operationId->toString(), -12)),
            1,
            new DateTimeImmutable('2026-09-11T00:00:03Z'),
        );
        $records = [
            $this->record(
                $operationId,
                $tenant,
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new PostgreSqlAuditedValue('private-value')),
            ),
            $this->record($operationId, $tenant, 2, JournalEvent::OperationAccepted, new EmptyJournalData()),
            $this->record($operationId, $tenant, 3, JournalEvent::AttemptStarted, new EmptyJournalData(), $attempt),
            $this->record($operationId, $tenant, 4, JournalEvent::AttemptSucceeded, new EmptyJournalData(), $attempt),
            $this->record(
                $operationId,
                $tenant,
                5,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new PostgreSqlAuditedOutcome('ready', $private)),
                $attempt,
            ),
        ];
        foreach ($records as $record) {
            $this->journal->append($record);
        }
        $this->connection->executeStatement(
            'UPDATE "' . $this->schema . '"."operations"
             SET state = :state, next_sequence = 6, attempt_number = 1,
                 current_attempt_id = NULL, current_attempt_started_at = NULL
             WHERE operation_id = :operation_id',
            ['state' => LifecycleState::Completed->value, 'operation_id' => $operationId->toString()],
        );
        $this->outcomes->save(
            new OutcomeRecord(
                $operationId,
                new PostgreSqlAuditedOutcome('ready', $private),
                new DateTimeImmutable('2026-09-11T00:00:06Z'),
            ),
        );
    }

    private function record(
        OperationId $operationId,
        ?TenantRef $tenant,
        int $sequence,
        JournalEvent $event,
        \BlackOps\Journal\JournalData $data,
        ?JournalAttempt $attempt = null,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf(
                '019f80ab-5000-7000-8000-%012d',
                ((int) substr($operationId->toString(), -6) * 10) + $sequence,
            )),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-09-11T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $operationId,
                self::OPERATION_TYPE,
                1,
                'deferred',
                CorrelationId::fromString('019f80ab-6000-7000-8000-' . substr($operationId->toString(), -12)),
                actorContext: new ActorContext(
                    new ActorRef('origin-a', 'customer'),
                    null,
                    new ActorRef('execution-a', 'worker'),
                ),
                tenant: $tenant,
            ),
            $attempt,
            $data,
        );
    }

    private function moveSubject(OperationId $operationId, TenantRef $tenant): void
    {
        $parameters = [
            'tenant_type' => $tenant->type(),
            'tenant_id' => $tenant->id(),
            'operation_id' => $operationId->toString(),
        ];
        $this->probe->executeStatement(
            'UPDATE "' . $this->schema . '"."operations"
             SET tenant_type = :tenant_type, tenant_id = :tenant_id
             WHERE operation_id = :operation_id',
            $parameters,
        );
        $this->probe->executeStatement(
            'UPDATE "' . $this->schema . '"."journal"
             SET tenant_type = :tenant_type, tenant_id = :tenant_id
             WHERE operation_id = :operation_id',
            $parameters,
        );
    }

    private function removeSubject(OperationId $operationId): void
    {
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."outcomes" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."journal" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."operations" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
    }

    private function driftOriginActor(OperationId $operationId, ActorRef $actor): void
    {
        $parameters = [
            'origin_actor_type' => $actor->type(),
            'origin_actor_id' => $actor->id(),
            'operation_id' => $operationId->toString(),
        ];
        $this->probe->executeStatement(
            'UPDATE "' . $this->schema . '"."operations"
             SET origin_actor_type = :origin_actor_type, origin_actor_id = :origin_actor_id
             WHERE operation_id = :operation_id',
            $parameters,
        );
        $this->probe->executeStatement(
            'UPDATE "' . $this->schema . '"."journal"
             SET origin_actor_type = :origin_actor_type, origin_actor_id = :origin_actor_id
             WHERE operation_id = :operation_id',
            $parameters,
        );
    }

    /** @return list<array{action_type:string,protected_body:string}> */
    private function auditRows(OperationId $operationId, ?TenantRef $tenant): array
    {
        $tenantWhere = $tenant === null
            ? 'tenant_type IS NULL AND tenant_id IS NULL'
            : 'tenant_type = :tenant_type AND tenant_id = :tenant_id';
        $parameters = [
            'resource_id' => (string) $this->opaqueIds->derive(
                AuditOpaqueIdPurpose::Resource,
                $operationId->toString(),
            ),
        ];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type();
            $parameters['tenant_id'] = $tenant->id();
        }
        /** @var list<array{action_type:string,protected_body:string}> $rows */
        $rows = $this->probe->fetchAllAssociative(
            'SELECT action_type, encode(protected_body, \'hex\') AS protected_body FROM "'
            . $this->schema
            . '"."audit_records"
             WHERE resource_id = :resource_id AND '
            . $tenantWhere
            . ' ORDER BY recorded_at, record_id',
            $parameters,
        );

        return $rows;
    }

    /** @param list<string> $phases */
    private function assertPhases(OperationId $operationId, ?TenantRef $tenant, array $phases): void
    {
        $rows = $this->auditRows($operationId, $tenant);
        self::assertSame(
            array_map(static fn(string $phase): string => 'access.operation_status.' . $phase, $phases),
            array_column($rows, 'action_type'),
        );
        $this->assertProtectedRowsContainNoStatusPayload($rows);
    }

    /** @param list<array{action_type:string,protected_body:string}> $rows */
    private function assertProtectedRowsContainNoStatusPayload(array $rows): void
    {
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertStringStartsWith('424f5044', $row['protected_body']);
            self::assertStringNotContainsString('70726976617465', strtolower($row['protected_body']));
        }
    }

    private function assertAuditDraftsContainNoDecodedStatus(): void
    {
        $drafts = [...$this->auditWriter->requested, ...$this->auditWriter->terminals];
        self::assertNotEmpty($drafts);
        foreach ($drafts as $draft) {
            $value = $draft->toArray();
            self::assertSame(
                [
                    'schemaVersion',
                    'kind',
                    'event',
                    'action',
                    'actor',
                    'resource',
                    'outcome',
                    'policy',
                    'tenant',
                    'occurredAt',
                    'source',
                    'correlation',
                    'evidence',
                ],
                array_keys($value),
            );
            self::assertSame(['status', 'code'], array_keys($draft->outcome->toArray()));
            self::assertSame([], $draft->correlation->toArray());
            self::assertSame(['version' => 1], $draft->evidence->toArray());
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            self::assertIsString($encoded);
            foreach ([
                'ready-private',
                'expired-private',
                'named-private',
                'global-private',
                'denied-private',
                'foreign-private',
                'moved-private',
                'removed-private',
                'drift-private',
                'terminal-private',
                'append-failure-private',
                'private-value',
                'payload',
                'context',
            ] as $canary) {
                self::assertStringNotContainsString($canary, strtolower($encoded));
            }
        }
    }

    private function assertStoredStatusPayloadIsEncrypted(OperationId $operationId): void
    {
        $row = $this->probe->fetchAssociative(
            'SELECT encode(o.encoded_payload, \'hex\') AS operation_payload,
                    encode(o.encoded_context, \'hex\') AS operation_context,
                    encode(j.encoded_record, \'hex\') AS journal_payload,
                    encode(c.encoded_payload, \'hex\') AS outcome_payload
             FROM "'
            . $this->schema
            . '"."operations" o
             JOIN "'
            . $this->schema
            . '"."journal" j ON j.operation_id = o.operation_id
             JOIN "'
            . $this->schema
            . '"."outcomes" c ON c.operation_id = o.operation_id
             WHERE o.operation_id = :operation_id
             ORDER BY j.sequence
             LIMIT 1',
            ['operation_id' => $operationId->toString()],
        );
        self::assertIsArray($row);
        foreach (['operation_payload', 'operation_context', 'journal_payload', 'outcome_payload'] as $key) {
            self::assertIsString($row[$key]);
            self::assertStringStartsWith('424f5044', $row[$key]);
            self::assertStringNotContainsString('70726976617465', strtolower($row[$key]));
        }
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . $this->schema . '"."audit_outbox_records"');
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString(sprintf('019f80ab-1000-7000-8000-%012d', $number));
    }

    private function tenant(string $id): TenantRef
    {
        return new TenantRef('customer', $id);
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

final class PostgreSqlAuditedAuditDraftWriter implements AuditReadEventWriter
{
    /** @var list<AuditRecordDraft> */
    public array $requested = [];

    /** @var list<AuditRecordDraft> */
    public array $terminals = [];

    public function __construct(
        private readonly AuditReadEventWriter $delegate,
    ) {}

    public function requested(
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): void {
        $this->requested[] = $draft;
        $this->delegate->requested($basis, $draft);
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        $this->terminals[] = $draft;

        return $this->delegate->terminal($context, $draft);
    }
}

final readonly class PostgreSqlAuditedOperation implements Operation {}

final readonly class PostgreSqlAuditedValue implements OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class PostgreSqlAuditedOutcome implements Outcome
{
    public function __construct(
        public string $status,
        public string $private,
    ) {}
}

final class PostgreSqlAuditedStatusAuthorizer implements OperationStatusAuthorizer
{
    public int $calls = 0;

    public function __construct(
        private readonly OperationStatusAuthorizationDecision $decision,
        private readonly ?Closure $callback = null,
    ) {}

    public function decide(
        #[\SensitiveParameter]
        OperationStatusAuthorizationRequest $request,
    ): OperationStatusAuthorizationDecision {
        ++$this->calls;
        if ($this->callback !== null) {
            ($this->callback)($request);
        }

        return $this->decision;
    }
}

final class PostgreSqlAuditedStatusKeyProvider implements StorageKeyProvider
{
    public int $calls = 0;

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key('test-key', $tenant, $purpose);
    }

    public function key(#[\SensitiveParameter] string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        ++$this->calls;
        if ($keyId !== 'test-key') {
            throw new \InvalidArgumentException('Unknown test storage key.');
        }

        return new StorageKey($keyId, str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function reset(): void
    {
        $this->calls = 0;
    }
}

final class PostgreSqlAuditedStatusConnection extends Connection
{
    /** @var list<int> */
    public array $requestedVisibleBeforeSubject = [];

    /** @var list<string> */
    public array $commitModes = [];

    /** @var list<string> */
    public array $transactionIsolations = [];

    /** @var list<string> */
    public array $transactionReadOnlyStates = [];

    public int $subjectReadAttempts = 0;

    public int $auditAppendAttempts = 0;

    public int $rollbackAttempts = 0;

    public ?string $auditAppendFailure = null;

    public int $readOnlyCommits = 0;

    public int $commitAttempts = 0;

    public ?string $terminalCommitFailure = null;

    private bool $postState = false;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly string $schema,
        private readonly Connection $probe,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        if (str_contains($query, '"' . $this->schema . '"."operations"') && str_contains($query, 'origin_actor_type')) {
            ++$this->subjectReadAttempts;
            $records = new PostgreSqlAuditSchema($this->schema)->recordsTable();
            $this->requestedVisibleBeforeSubject[] = (int) $this->probe->fetchOne(
                "SELECT count(*) FROM {$records} WHERE action_type = 'access.operation_status.requested'",
            );
        }

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $value = parent::fetchOne($query, $params, $types);
        if ($this->isTransactionActive() && trim($query) === 'SHOW transaction_isolation') {
            $this->transactionIsolations[] = (string) $value;
        }
        if ($this->isTransactionActive() && trim($query) === 'SHOW transaction_read_only') {
            $this->transactionReadOnlyStates[] = (string) $value;
        }
        if (str_contains($query, 'WITH new_segment')) {
            ++$this->auditAppendAttempts;
            $shouldFail = match ($this->auditAppendFailure) {
                'requested' => $this->auditAppendAttempts === 1,
                'terminal' => $this->auditAppendAttempts === 2,
                default => false,
            };
            if ($shouldFail) {
                throw new \RuntimeException('audit append failure');
            }
        }

        return $value;
    }

    public function rollBack(): void
    {
        ++$this->rollbackAttempts;
        parent::rollBack();
    }

    public function commit(): void
    {
        ++$this->commitAttempts;
        $readOnly = (string) $this->fetchOne('SHOW transaction_read_only');
        $this->commitModes[] = $readOnly;
        if ($readOnly === 'on') {
            ++$this->readOnlyCommits;
        }
        if ($this->terminalCommitFailure === 'before' && $this->commitAttempts === 3) {
            throw new \RuntimeException('terminal commit failure');
        }
        parent::commit();
        if ($this->terminalCommitFailure === 'after' && $this->commitAttempts === 3) {
            throw new \RuntimeException('terminal commit then throw');
        }
        if ($this->terminalCommitFailure === 'poststate' && $this->commitAttempts === 3) {
            $this->postState = true;
        }
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->postState ? 1 : parent::getTransactionNestingLevel();
    }
}

final class PostgreSqlAuditedAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        return $this->key('audit-key', $tenant, $purpose);
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        if ($keyId !== 'audit-key') {
            throw new \InvalidArgumentException('Unknown audit key.');
        }

        return new StorageKey($keyId, str_repeat('a', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }
}

final class PostgreSqlAuditedAuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'audit-nonce-' . $this->calls, binary: true), 0, 24);
    }
}

final class PostgreSqlAuditedAuditClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-11T00:00:10.000000Z');
    }
}

final class PostgreSqlAuditedAuditUuidGenerator implements AuditUuidv7Generator
{
    private int $calls = 0;

    public function generate(DateTimeImmutable $time): string
    {
        ++$this->calls;

        return sprintf('019f80ab-2000-7000-8000-%012x', $this->calls);
    }
}

final class PostgreSqlAuditedReadNonceSource implements OperationReadNonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'read-nonce-' . $this->calls, binary: true), 0, 16);
    }
}

final class PostgreSqlAuditedReadClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-11T00:00:11.000000Z');
    }
}

final class PostgreSqlAuditedExecutionUuidGenerator implements Uuidv7Generator
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return sprintf('019f80ab-3000-7000-8000-%012x', $this->calls);
    }
}
