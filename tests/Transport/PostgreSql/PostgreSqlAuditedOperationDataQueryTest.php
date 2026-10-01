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
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
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
use BlackOps\Internal\OperationData\AuditedOperationJournalQuery;
use BlackOps\Internal\OperationData\AuditedOperationOutcomeQuery;
use BlackOps\Internal\OperationData\OperationDataSubject;
use BlackOps\Internal\OperationData\OperationDataSubjectReader;
use BlackOps\Internal\OperationData\OperationJournalReadExecutionSource;
use BlackOps\Internal\OperationData\OperationOutcomeReadExecutionSource;
use BlackOps\Internal\OperationData\PostgreSqlOperationDataSubjectReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedOutcomeReader;
use BlackOps\Internal\OperationData\TenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\TenantScopedOutcomeReader;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalUnavailable;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeUnavailable;
use BlackOps\Outcome\OutcomeRecord;
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
use Traversable;

/** @mago-expect lint:too-many-methods */
/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
final class PostgreSqlAuditedOperationDataQueryTest extends TestCase
{
    private const string OPERATION_TYPE = 'operation.data.postgresql';

    private const string SCHEMA_PREFIX = 'blackops_p23_d2j_';

    private const string AUDIT_KEY = '0123456789abcdef0123456789abcdef';

    private string $schema;

    private Connection $admin;

    private Connection $probe;

    private PostgreSqlD2JConnection $connection;

    private PostgreSqlDeferredOperationSender $sender;

    private PostgreSqlCanonicalJournalStore $journal;

    private PostgreSqlOutcomeStore $outcomes;

    private BopdEnvelopeCodec $operationProtection;

    private PostgreSqlD2JStorageKeyProvider $storageKeys;

    private OperationReadAuditSessionFactory $journalSessions;

    private OperationReadAuditSessionFactory $outcomeSessions;

    private PostgreSqlD2JAuditDraftWriter $auditWriter;

    private HmacAuditOpaqueIdDeriver $opaqueIds;

    private PostgreSqlD2JReadNonceSource $readNonces;

    private PostgreSqlD2JExecutionUuidGenerator $journalExecution;

    private PostgreSqlD2JExecutionUuidGenerator $outcomeExecution;

    private PostgreSqlD2JAuditUuidGenerator $auditUuids;

    protected function setUp(): void
    {
        $this->schema = self::SCHEMA_PREFIX . bin2hex(random_bytes(6));
        $this->admin = $this->connection();
        $this->probe = $this->connection();
        $base = $this->connection();
        $params = $base->getParams();
        $driver = $base->getDriver();
        $base->close();
        $this->connection = new PostgreSqlD2JConnection($params, $driver, $this->schema, $this->probe);
        $this->admin->executeStatement('CREATE SCHEMA "' . $this->schema . '"');

        $this->storageKeys = new PostgreSqlD2JStorageKeyProvider();
        $this->operationProtection = new BopdEnvelopeCodec($this->storageKeys);
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            $this->operationProtection,
            $this->schema,
        );
        $this->sender->migrate();
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            $this->operationProtection,
            $this->schema,
        );
        $this->journal->migrate();
        $this->outcomes = new PostgreSqlOutcomeStore($this->connection, $this->operationProtection, $this->schema);
        $this->outcomes->migrate();

        $recordSchema = new PostgreSqlAuditSchema($this->schema);
        $outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($this->schema));
        foreach ([...$recordSchema->statements(), ...$outboxSchema->statements()] as $statement) {
            $this->admin->executeStatement($statement);
        }

        $this->opaqueIds = new HmacAuditOpaqueIdDeriver(self::AUDIT_KEY);
        [$this->journalSessions, $this->outcomeSessions] = $this->auditSessions($recordSchema, $outboxSchema);
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
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

    public function testFoundJournalAndOutcomeUseActualTenantReadersAndTwoRequiredAuditRoots(): void
    {
        $operationId = $this->id(1);
        $tenant = $this->tenant('tenant-a');
        $this->createCompleted($operationId, $tenant, 'operation-data-private-canary');
        $purpose = $this->purpose();
        $expectedRequested = 1;
        $authorizer =
            new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow(), function (OperationDataReadAuthorizationRequest $request) use (
                &$expectedRequested,
                $operationId,
                $tenant,
            ): void {
                self::assertSame($expectedRequested, $this->requestedAuditCount());
                self::assertSame($operationId->toString(), $request->operationId()->toString());
                self::assertSame($tenant->type(), $request->originTenant()?->type());
                self::assertSame($tenant->id(), $request->originTenant()?->id());
                self::assertSame('application.view', $request->purpose()->code());
                ++$expectedRequested;
            });

        $journal = $this->journalQuery($authorizer);
        $journalResult = $journal->records($operationId, new ActorRef('reader-a', 'user'), $tenant, $purpose);
        self::assertInstanceOf(OperationJournalFound::class, $journalResult);
        self::assertCount(5, $journalResult->records());

        $outcome = $this->outcomeQuery($authorizer);
        $outcomeResult = $outcome->find($operationId, new ActorRef('reader-a', 'user'), $tenant, $purpose);
        self::assertInstanceOf(OperationOutcomeFound::class, $outcomeResult);
        self::assertSame('operation-data-private-canary', $outcomeResult->record()->outcome()->private);

        self::assertSame([1, 1, 2, 2], $this->connection->requestedVisibleBeforeSubject);
        self::assertSame(4, $this->connection->beginAttempts);
        self::assertSame(4, $this->connection->commitAttempts);
        self::assertSame(4, $this->connection->auditAppendAttempts);
        self::assertSame(1, $this->connection->protectedJournalReadAttempts);
        self::assertSame(1, $this->connection->protectedOutcomeReadAttempts);
        self::assertSame(0, $this->connection->rollbackAttempts);
        self::assertSame(0, $this->connection->readOnlyCommits);
        self::assertNotContains('on', $this->connection->transactionReadOnlyStates);
        self::assertNotContains('repeatable read', $this->connection->transactionIsolations);
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        self::assertSame(2, $this->readNonces->calls);
        self::assertSame(1, $this->journalExecution->calls);
        self::assertSame(1, $this->outcomeExecution->calls);
        self::assertGreaterThan(0, $this->storageKeys->calls);

        $rows = $this->auditRows($operationId, $tenant);
        self::assertSame(
            [
                'access.operation_journal.requested',
                'access.operation_journal.accessed',
                'access.operation_outcome.requested',
                'access.operation_outcome.accessed',
            ],
            array_column($rows, 'action_type'),
        );
        $this->assertProtectedAuditRowsContainNoOperationData($rows);
        $this->assertAuditDraftsContainNoOperationData([
            'operation-data-private-canary',
            'operation-payload-canary',
            'operation-context-canary',
            'application.view',
        ]);
        $this->assertDistinctAuditIdentityOwners($rows);
        self::assertSame(0, $this->outboxCount());
        $this->assertStoredOperationPayloadsAreEncrypted($operationId, [
            'operation-data-private-canary',
            'operation-payload-canary',
            'operation-context-canary',
        ]);
    }

    public function testTenantScopedIsolationCoversGlobalNamedForeignDeniedMissingAndEmptyRows(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $namedId = $this->id(10);
        $globalId = $this->id(11);
        $deniedId = $this->id(12);
        $foreignId = $this->id(13);
        $emptyId = $this->id(14);
        $missingId = $this->id(15);
        $this->createCompleted($namedId, $tenantA, 'named-private-canary');
        $this->createCompleted($globalId, null, 'global-private-canary');
        $this->createCompleted($deniedId, $tenantA, 'denied-private-canary');
        $this->createCompleted($foreignId, $tenantB, 'foreign-private-canary');
        $this->createSubject($emptyId, $tenantA);
        $purpose = $this->purpose();

        $allow = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $journal = $this->journalQuery($allow);
        $outcome = $this->outcomeQuery($allow);
        self::assertInstanceOf(OperationJournalFound::class, $journal->records($namedId, null, $tenantA, $purpose));
        self::assertInstanceOf(OperationOutcomeFound::class, $outcome->find($namedId, null, $tenantA, $purpose));
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records($namedId, null, null, $purpose));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find($namedId, null, null, $purpose));
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $namedId,
            null,
            new TenantRef('account', 'tenant-a'),
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find($namedId, null, $tenantB, $purpose));
        self::assertInstanceOf(\BlackOps\OperationData\OperationJournalFound::class, $journal->records(
            $globalId,
            null,
            null,
            $purpose,
        ));
        self::assertInstanceOf(\BlackOps\OperationData\OperationOutcomeFound::class, $outcome->find(
            $globalId,
            null,
            null,
            $purpose,
        ));
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $globalId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find($globalId, null, $tenantA, $purpose));

        $deny = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::deny());
        self::assertInstanceOf(OperationJournalUnavailable::class, $this->journalQuery($deny)->records(
            $deniedId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertSame(1, $deny->calls);
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $this->outcomeQuery($deny)->find(
            $deniedId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertSame(2, $deny->calls);

        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $emptyId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find($emptyId, null, $tenantA, $purpose));
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $missingId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find(
            $missingId,
            null,
            $tenantA,
            $purpose,
        ));

        $this->storageKeys->reset();
        $this->connection->protectedJournalReadAttempts = 0;
        $this->connection->protectedOutcomeReadAttempts = 0;
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $foreignId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find(
            $foreignId,
            null,
            $tenantA,
            $purpose,
        ));
        self::assertSame(0, $this->storageKeys->calls);
        self::assertSame(0, $this->connection->protectedJournalReadAttempts);
        self::assertSame(0, $this->connection->protectedOutcomeReadAttempts);

        $this->assertAuditPhases($namedId, $tenantA, 'journal', ['requested', 'accessed']);
        $this->assertAuditPhases($namedId, $tenantA, 'outcome', ['requested', 'accessed']);
        $this->assertAuditPhases($namedId, null, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($namedId, null, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases(
            $namedId,
            new TenantRef('account', 'tenant-a'),
            'journal',
            ['requested', 'unavailable'],
        );
        $this->assertAuditPhases($namedId, $tenantB, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases($globalId, null, 'journal', ['requested', 'accessed']);
        $this->assertAuditPhases($globalId, null, 'outcome', ['requested', 'accessed']);
        $this->assertAuditPhases($globalId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($globalId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases($deniedId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($deniedId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases($emptyId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($emptyId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases($missingId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($missingId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditPhases($foreignId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($foreignId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoOperationData([
            'named-private-canary',
            'global-private-canary',
            'denied-private-canary',
            'foreign-private-canary',
            'application.view',
        ]);
        self::assertSame(0, $this->outboxCount());
    }

    public function testWrongScopeSubjectIsRejectedBeforeAllowAuthorizerAndProtectedBlobAccess(): void
    {
        $operationId = $this->id(20);
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $this->createCompleted($operationId, $tenantB, 'foreign-subject-private-canary');
        $subject = new OperationDataSubject(
            $operationId,
            self::OPERATION_TYPE,
            new ActorRef('origin-a', 'customer'),
            $tenantB,
        );
        $subjects = new PostgreSqlD2JFixedSubjectReader($subject);
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $this->storageKeys->reset();

        self::assertInstanceOf(OperationJournalUnavailable::class, $this->journalQuery($authorizer, $subjects)->records(
            $operationId,
            null,
            $tenantA,
            $this->purpose(),
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $this->outcomeQuery($authorizer, $subjects)->find(
            $operationId,
            null,
            $tenantA,
            $this->purpose(),
        ));

        self::assertSame(0, $authorizer->calls);
        self::assertSame(0, $this->storageKeys->calls);
        self::assertSame(0, $this->connection->subjectReadAttempts);
        $this->assertAuditPhases($operationId, $tenantA, 'journal', ['requested', 'unavailable']);
        $this->assertAuditPhases($operationId, $tenantA, 'outcome', ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoOperationData(['foreign-subject-private-canary', 'application.view']);
    }

    public function testOneAdapterCanAlternateCallersWithoutRetainingOperationDataState(): void
    {
        $tenant = $this->tenant('tenant-a');
        $first = $this->id(25);
        $second = $this->id(26);
        $this->createCompleted($first, $tenant, 'alternate-first-private-canary');
        $this->createCompleted($second, $tenant, 'alternate-second-private-canary');
        $purpose = $this->purpose();
        $actors = [];
        $authorizer =
            new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow(), function (OperationDataReadAuthorizationRequest $request) use (
                &$actors,
            ): void {
                $actors[] = $request->currentActor()?->id();
            });
        $journal = $this->journalQuery($authorizer);
        self::assertInstanceOf(OperationJournalFound::class, $journal->records(
            $first,
            new ActorRef('caller-a', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationJournalFound::class, $journal->records(
            $second,
            new ActorRef('caller-b', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationJournalFound::class, $journal->records(
            $first,
            new ActorRef('caller-c', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertSame(['caller-a', 'caller-b', 'caller-c'], $actors);

        $outcome = $this->outcomeQuery($authorizer);
        self::assertInstanceOf(OperationOutcomeFound::class, $outcome->find(
            $first,
            new ActorRef('caller-a', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeFound::class, $outcome->find(
            $second,
            new ActorRef('caller-b', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeFound::class, $outcome->find(
            $first,
            new ActorRef('caller-c', 'user'),
            $tenant,
            $purpose,
        ));
        self::assertSame(['caller-a', 'caller-b', 'caller-c', 'caller-a', 'caller-b', 'caller-c'], $actors);
        self::assertSame(3, $this->journalExecution->calls);
        self::assertSame(3, $this->outcomeExecution->calls);
        self::assertSame(6, $this->readNonces->calls);
        $this->assertAuditPhases($first, $tenant, 'journal', ['requested', 'accessed', 'requested', 'accessed']);
        $this->assertAuditPhases($second, $tenant, 'journal', ['requested', 'accessed']);
        $this->assertAuditPhases($first, $tenant, 'outcome', ['requested', 'accessed', 'requested', 'accessed']);
        $this->assertAuditPhases($second, $tenant, 'outcome', ['requested', 'accessed']);
        $this->assertAuditDraftsContainNoOperationData([
            'alternate-first-private-canary',
            'alternate-second-private-canary',
            'application.view',
        ]);
    }

    public function testJournalConsumesEveryRowBeforeTerminalAndDoesNotReleasePartialPrefix(): void
    {
        $operationId = $this->id(30);
        $tenant = $this->tenant('tenant-a');
        $this->createCompleted($operationId, $tenant, 'journal-iteration-private-canary');
        $this->connection->executeStatement(
            'UPDATE "'
            . $this->schema
            . '"."journal" SET encoded_record = decode(\'424f5044\', \'hex\') '
            . 'WHERE operation_id = :operation_id AND sequence = 2',
            ['operation_id' => $operationId->toString()],
        );

        try {
            $this->journalQuery(new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()))->records(
                $operationId,
                new ActorRef('reader-a', 'user'),
                $tenant,
                $this->purpose(),
            );
            self::fail('The later journal row failure was swallowed.');
        } catch (OperationJournalQueryException $exception) {
            self::assertSame(OperationJournalQueryException::DECODE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame([1, 1], $this->connection->requestedVisibleBeforeSubject);
        self::assertSame(1, count($this->auditWriter->terminals));
        $this->assertAuditPhases($operationId, $tenant, 'journal', ['requested', 'unavailable']);
        self::assertSame(0, $this->outboxCount());
        $this->assertAuditDraftsContainNoOperationData(['journal-iteration-private-canary', 'application.view']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function queryFailureCodes(): iterable
    {
        foreach (['journal', 'outcome'] as $resource) {
            $codes = $resource === 'journal'
                ? [
                    OperationJournalQueryException::AUTHORIZATION_FAILED,
                    OperationJournalQueryException::STORAGE_FAILED,
                    OperationJournalQueryException::PROTECTION_FAILED,
                    OperationJournalQueryException::DECODE_FAILED,
                    OperationJournalQueryException::INTEGRITY_FAILED,
                ]
                : [
                    OperationOutcomeQueryException::AUTHORIZATION_FAILED,
                    OperationOutcomeQueryException::STORAGE_FAILED,
                    OperationOutcomeQueryException::PROTECTION_FAILED,
                    OperationOutcomeQueryException::DECODE_FAILED,
                    OperationOutcomeQueryException::INTEGRITY_FAILED,
                ];
            foreach ($codes as $code) {
                yield $resource . ':' . $code => [$resource, $code];
            }
        }
    }

    #[DataProvider('queryFailureCodes')]
    public function testMatchingResourceFailureKeepsCodeAndExceptionIdentityAfterUnavailableTerminal(
        string $resource,
        string $code,
    ): void {
        $operationId = $this->id(40);
        $tenant = $this->tenant('tenant-a');
        $subject = new PostgreSqlD2JFixedSubjectReader(
            new OperationDataSubject($operationId, self::OPERATION_TYPE, new ActorRef('origin-a', 'customer'), $tenant),
        );
        $failure = $this->queryFailure($resource, $code);
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        try {
            if ($resource === 'journal') {
                $this->journalQuery($authorizer, $subject, new PostgreSqlD2JThrowingJournalReader($failure))->records(
                    $operationId,
                    null,
                    $tenant,
                    $this->purpose(),
                );
            } else {
                $this->outcomeQuery($authorizer, $subject, new PostgreSqlD2JThrowingOutcomeReader($failure))->find(
                    $operationId,
                    null,
                    $tenant,
                    $this->purpose(),
                );
            }
            self::fail('The matching resource failure was swallowed.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $caught) {
            self::assertSame($failure, $caught);
            self::assertSame($code, $caught->queryCode());
            self::assertNull($caught->getPrevious());
        }

        self::assertSame(1, $authorizer->calls);
        $this->assertAuditPhases($operationId, $tenant, $resource, ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoOperationData(['application.view']);
    }

    /** @return iterable<string, array{string}> */
    public static function resources(): iterable
    {
        yield 'journal' => ['journal'];
        yield 'outcome' => ['outcome'];
    }

    #[DataProvider('resources')]
    public function testUnexpectedReaderThrowableMapsToStorageWithoutCause(string $resource): void
    {
        $operationId = $this->id(50);
        $tenant = $this->tenant('tenant-a');
        $subject = new PostgreSqlD2JFixedSubjectReader(
            new OperationDataSubject($operationId, self::OPERATION_TYPE, new ActorRef('origin-a', 'customer'), $tenant),
        );
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $unexpected = new ThrowableForPostgreSqlD2J('unexpected-reader-canary');
        try {
            if ($resource === 'journal') {
                $this->journalQuery(
                    $authorizer,
                    $subject,
                    new PostgreSqlD2JUnexpectedJournalReader($unexpected),
                )->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery($authorizer, $subject, new PostgreSqlD2JUnexpectedOutcomeReader($unexpected))->find(
                    $operationId,
                    null,
                    $tenant,
                    $this->purpose(),
                );
            }
            self::fail('The unexpected reader throwable was swallowed.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('unexpected-reader-canary', $exception->getMessage());
        }

        self::assertSame(1, $authorizer->calls);
        $this->assertAuditPhases($operationId, $tenant, $resource, ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoOperationData(['unexpected-reader-canary', 'application.view']);
    }

    #[DataProvider('resources')]
    public function testActualSubjectReadFailurePublishesUnavailableTerminal(string $resource): void
    {
        $operationId = $this->id(60);
        $tenant = $this->tenant('tenant-a');
        $this->createSubject($operationId, $tenant);
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $this->connection->readFailure = true;

        try {
            if ($resource === 'journal') {
                $this->journalQuery($authorizer)->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery($authorizer)->find($operationId, null, $tenant, $this->purpose());
            }
            self::fail('The subject read failure was swallowed.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $this->connection->subjectReadAttempts);
        self::assertSame(0, $authorizer->calls);
        $this->assertAuditPhases($operationId, $tenant, $resource, ['requested', 'unavailable']);
        $this->assertAuditDraftsContainNoOperationData(['application.view']);
    }

    #[DataProvider('resources')]
    public function testRequestedAuditFailureDoesNoSubjectReadOrTerminalAttempt(string $resource): void
    {
        $operationId = $this->id(70);
        $tenant = $this->tenant('tenant-a');
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $this->connection->auditAppendFailure = 'requested';

        try {
            if ($resource === 'journal') {
                $this->journalQuery($authorizer)->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery($authorizer)->find($operationId, null, $tenant, $this->purpose());
            }
            self::fail('The requested Audit failure was swallowed.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $this->connection->auditAppendAttempts);
        self::assertSame(1, $this->connection->beginAttempts);
        self::assertSame(0, $this->connection->commitAttempts);
        self::assertSame(1, $this->connection->rollbackAttempts);
        self::assertSame(0, $this->connection->subjectReadAttempts);
        self::assertSame(0, $authorizer->calls);
        self::assertSame([], $this->auditWriter->terminals);
        self::assertSame([], $this->auditRows($operationId, $tenant));
        self::assertSame(0, $this->outboxCount());
    }

    #[DataProvider('resources')]
    public function testIdleGuardRejectsAmbientAndAutoCommitFalseWithoutRepair(string $resource): void
    {
        $operationId = $this->id(80);
        $tenant = $this->tenant('tenant-a');
        $authorizer = new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $this->connection->beginTransaction();
        $commitBefore = $this->connection->commitAttempts;
        $rollbackBefore = $this->connection->rollbackAttempts;

        try {
            if ($resource === 'journal') {
                $this->journalQuery($authorizer)->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery($authorizer)->find($operationId, null, $tenant, $this->purpose());
            }
            self::fail('The ambient transaction was accepted.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
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
        $this->connection->setAutoCommit(false);
        $commitBefore = $this->connection->commitAttempts;
        $rollbackBefore = $this->connection->rollbackAttempts;
        try {
            if ($resource === 'journal') {
                $this->journalQuery($authorizer)->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery($authorizer)->find($operationId, null, $tenant, $this->purpose());
            }
            self::fail('Auto-commit false was accepted.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($commitBefore, $this->connection->commitAttempts);
        self::assertSame($rollbackBefore, $this->connection->rollbackAttempts);
        self::assertFalse($this->connection->isTransactionActive());
        self::assertFalse($this->connection->isAutoCommit());
        self::assertSame(0, $authorizer->calls);
        self::assertSame([], $this->auditWriter->requested);
        self::assertSame([], $this->auditWriter->terminals);
        $this->connection->setAutoCommit(true);
    }

    /** @return iterable<string, array{string, string}> */
    public static function terminalFailureModes(): iterable
    {
        foreach (['journal', 'outcome'] as $resource) {
            foreach (['append', 'before', 'after', 'poststate'] as $mode) {
                yield $resource . ':' . $mode => [$resource, $mode];
            }
        }
    }

    #[DataProvider('terminalFailureModes')]
    public function testTerminalFailureDoesNotRetryAndPreservesOnlyCommittedPrefix(string $resource, string $mode): void
    {
        $operationId = $this->id(90);
        $tenant = $this->tenant('tenant-a');
        $this->createCompleted($operationId, $tenant, 'terminal-private-canary');
        if ($mode === 'append') {
            $this->connection->auditAppendFailure = 'terminal';
        } else {
            $this->connection->terminalCommitFailure = $mode;
        }

        try {
            if ($resource === 'journal') {
                $this->journalQuery(
                    new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                )->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery(new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()))->find(
                    $operationId,
                    null,
                    $tenant,
                    $this->purpose(),
                );
            }
            self::fail('The terminal failure was swallowed.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
        }

        $expected = in_array($mode, ['after', 'poststate'], strict: true) ? ['requested', 'accessed'] : ['requested'];
        $this->assertAuditPhases($operationId, $tenant, $resource, $expected);
        self::assertSame(2, $this->connection->auditAppendAttempts);
        self::assertSame($mode === 'append' ? 1 : 2, $this->connection->commitAttempts);
        self::assertSame(1, count($this->auditWriter->terminals));
        if ($mode === 'poststate') {
            self::assertFalse($this->connection->isTransactionActive());
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
            self::assertSame(0, $this->connection->rollbackAttempts);
        }
        self::assertSame(0, $this->outboxCount());
        $this->assertAuditDraftsContainNoOperationData(['terminal-private-canary', 'application.view']);
    }

    #[DataProvider('resources')]
    public function testTerminalFailureSupersedesUnavailableResult(string $resource): void
    {
        $operationId = $this->id(100);
        $tenant = $this->tenant('tenant-a');
        $this->createSubject($operationId, $tenant);
        $this->connection->terminalCommitFailure = 'before';

        try {
            if ($resource === 'journal') {
                $this->journalQuery(
                    new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                )->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery(new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()))->find(
                    $operationId,
                    null,
                    $tenant,
                    $this->purpose(),
                );
            }
            self::fail('The terminal failure did not supersede unavailable.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNull($exception->getPrevious());
        }

        $this->assertAuditPhases($operationId, $tenant, $resource, ['requested']);
        self::assertSame(2, $this->connection->auditAppendAttempts);
        self::assertSame(2, $this->connection->commitAttempts);
        self::assertSame(1, count($this->auditWriter->terminals));
    }

    #[DataProvider('queryFailureCodes')]
    public function testTerminalFailureSupersedesEveryPendingSafeCode(string $resource, string $code): void
    {
        $operationId = $this->id(110);
        $tenant = $this->tenant('tenant-a');
        $subjects = new PostgreSqlD2JFixedSubjectReader(
            new OperationDataSubject($operationId, self::OPERATION_TYPE, new ActorRef('origin-a', 'customer'), $tenant),
        );
        $failure = $this->queryFailure($resource, $code);
        $this->connection->terminalCommitFailure = 'before';
        try {
            if ($resource === 'journal') {
                $this->journalQuery(
                    new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                    $subjects,
                    new PostgreSqlD2JThrowingJournalReader($failure),
                )->records($operationId, null, $tenant, $this->purpose());
            } else {
                $this->outcomeQuery(
                    new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                    $subjects,
                    new PostgreSqlD2JThrowingOutcomeReader($failure),
                )->find($operationId, null, $tenant, $this->purpose());
            }
            self::fail('The terminal failure did not supersede a pending safe code.');
        } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
            self::assertSame(
                $resource === 'journal'
                    ? OperationJournalQueryException::STORAGE_FAILED
                    : OperationOutcomeQueryException::STORAGE_FAILED,
                $exception->queryCode(),
            );
            self::assertNotSame($failure, $exception);
            self::assertNull($exception->getPrevious());
        }

        $this->assertAuditPhases($operationId, $tenant, $resource, ['requested']);
        self::assertSame(2, $this->connection->auditAppendAttempts);
        self::assertSame(2, $this->connection->commitAttempts);
        self::assertSame(1, count($this->auditWriter->terminals));
    }

    public function testInheritedPostgreSqlReaderFramesHideCanariesWithExceptionArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            foreach (['journal', 'outcome'] as $index => $resource) {
                $operationId = $this->id(120 + $index);
                $tenant = $this->tenant('trace-tenant-canary');
                $private = $resource . '-trace-private-canary';
                $this->createCompleted($operationId, $tenant, $private);
                if ($resource === 'journal') {
                    $this->connection->executeStatement(
                        'UPDATE "'
                        . $this->schema
                        . '"."journal" SET encoded_record = decode(\'424f5044\', \'hex\') '
                        . 'WHERE operation_id = :operation_id AND sequence = 2',
                        ['operation_id' => $operationId->toString()],
                    );
                } else {
                    $this->connection->executeStatement(
                        'UPDATE "'
                        . $this->schema
                        . '"."outcomes" SET encoded_payload = decode(\'424f5044\', \'hex\') '
                        . 'WHERE operation_id = :operation_id',
                        ['operation_id' => $operationId->toString()],
                    );
                }

                try {
                    if ($resource === 'journal') {
                        $this->journalQuery(
                            new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                        )->records(
                            $operationId,
                            new ActorRef('trace-actor-canary', 'user'),
                            $tenant,
                            OperationDataPurpose::fromString('trace-application-purpose-canary'),
                        );
                    } else {
                        $this->outcomeQuery(
                            new PostgreSqlD2JAuthorizer(OperationDataReadAuthorizationDecision::allow()),
                        )->find(
                            $operationId,
                            new ActorRef('trace-actor-canary', 'user'),
                            $tenant,
                            OperationDataPurpose::fromString('trace-application-purpose-canary'),
                        );
                    }
                    self::fail('The corrupt protected row was accepted.');
                } catch (OperationJournalQueryException|OperationOutcomeQueryException $exception) {
                    self::assertSame(
                        $resource === 'journal'
                            ? OperationJournalQueryException::DECODE_FAILED
                            : OperationOutcomeQueryException::DECODE_FAILED,
                        $exception->queryCode(),
                    );
                    $traceFrames = array_filter($exception->getTrace(), static fn(array $frame): bool => str_starts_with(
                        (string) ($frame['class'] ?? ''),
                        'BlackOps\\Internal\\',
                    ));
                    $trace = print_r($traceFrames, return: true) . var_export($traceFrames, return: true);
                    foreach ([
                        $operationId->toString(),
                        'trace-actor-canary',
                        'trace-tenant-canary',
                        'trace-application-purpose-canary',
                        $private,
                    ] as $canary) {
                        self::assertStringNotContainsString($canary, $trace, $resource);
                    }
                }
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /** @return array{0:OperationReadAuditSessionFactory,1:OperationReadAuditSessionFactory} */
    private function auditSessions(
        PostgreSqlAuditSchema $recordSchema,
        PostgreSqlAuditOutboxSchema $outboxSchema,
    ): array {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $records);
        $ingress = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $auditProtection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new PostgreSqlD2JAuditKeySource(),
            new PostgreSqlD2JAuditNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new PostgreSqlD2JAuditClock();
        $this->auditUuids = new PostgreSqlD2JAuditUuidGenerator();
        $recordStore = new PostgreSqlAuditRecordStore(
            $this->connection,
            $recordSchema,
            $clock,
            $this->auditUuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $auditProtection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $this->connection,
            $outboxSchema,
            $clock,
            $this->auditUuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $auditProtection,
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
        $this->auditWriter = new PostgreSqlD2JAuditDraftWriter(new AuditProtectedReadGate($delivery));
        $this->readNonces = new PostgreSqlD2JReadNonceSource();
        $this->journalExecution = new PostgreSqlD2JExecutionUuidGenerator('4000');
        $this->outcomeExecution = new PostgreSqlD2JExecutionUuidGenerator('5000');

        return [
            new OperationReadAuditSessionFactory(
                $this->auditWriter,
                $this->opaqueIds,
                $this->readNonces,
                new PostgreSqlD2JReadClock(),
                new OperationJournalReadExecutionSource($this->journalExecution),
                AuditSourceRuntime::Http,
                'postgresql-d2j-test',
            ),
            new OperationReadAuditSessionFactory(
                $this->auditWriter,
                $this->opaqueIds,
                $this->readNonces,
                new PostgreSqlD2JReadClock(),
                new OperationOutcomeReadExecutionSource($this->outcomeExecution),
                AuditSourceRuntime::Http,
                'postgresql-d2j-test',
            ),
        ];
    }

    private function journalQuery(
        OperationDataReadAuthorizer $authorizer,
        ?OperationDataSubjectReader $subjects = null,
        ?TenantScopedCanonicalJournalReader $reader = null,
        ?OperationReadAuditSessionFactory $sessions = null,
    ): AuditedOperationJournalQuery {
        return new AuditedOperationJournalQuery(
            $subjects ?? $this->subjects(),
            $authorizer,
            $reader ?? new PostgreSqlTenantScopedCanonicalJournalReader(
                $this->connection,
                $this->operationProtection,
                $this->schema,
            ),
            $sessions ?? $this->journalSessions,
            $this->connection,
        );
    }

    private function outcomeQuery(
        OperationDataReadAuthorizer $authorizer,
        ?OperationDataSubjectReader $subjects = null,
        ?TenantScopedOutcomeReader $reader = null,
        ?OperationReadAuditSessionFactory $sessions = null,
    ): AuditedOperationOutcomeQuery {
        return new AuditedOperationOutcomeQuery(
            $subjects ?? $this->subjects(),
            $authorizer,
            $reader ?? new PostgreSqlTenantScopedOutcomeReader(
                $this->connection,
                $this->operationProtection,
                $this->schema,
            ),
            $sessions ?? $this->outcomeSessions,
            $this->connection,
        );
    }

    private function subjects(): OperationDataSubjectReader
    {
        return new PostgreSqlOperationDataSubjectReader(
            new \BlackOps\Transport\PostgreSql\PostgreSqlStatusReader($this->connection, $this->schema),
        );
    }

    private function purpose(): OperationDataPurpose
    {
        return OperationDataPurpose::fromString('application.view');
    }

    private function createCompleted(OperationId $operationId, ?TenantRef $tenant, string $private): void
    {
        $this->createSubject($operationId, $tenant);
        $number = hexdec(substr($operationId->toString(), -4));
        $attempt = new \BlackOps\Journal\JournalAttempt(
            AttemptId::fromString('019f90ab-1100-7000-8000-' . sprintf('%012x', $number + 1)),
            1,
            new DateTimeImmutable('2026-09-14T00:00:03Z'),
        );
        $records = [
            $this->record(
                $operationId,
                $tenant,
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new PostgreSqlD2JValue('operation-value-canary')),
            ),
            $this->record($operationId, $tenant, 2, JournalEvent::OperationAccepted, new EmptyJournalData()),
            $this->record($operationId, $tenant, 3, JournalEvent::AttemptStarted, new EmptyJournalData(), $attempt),
            $this->record($operationId, $tenant, 4, JournalEvent::AttemptSucceeded, new EmptyJournalData(), $attempt),
            $this->record(
                $operationId,
                $tenant,
                5,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new PostgreSqlD2JOutcome('ready', $private)),
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
                new PostgreSqlD2JOutcome('ready', $private),
                new DateTimeImmutable('2026-09-14T00:00:06Z'),
            ),
        );
    }

    private function createSubject(OperationId $operationId, ?TenantRef $tenant): void
    {
        $this->sender->enqueue(
            new DeferredOperationMessage(
                $operationId,
                self::OPERATION_TYPE,
                1,
                '{"operation_payload":"operation-payload-canary"}',
                '{"operation_context":"operation-context-canary"}',
                new DateTimeImmutable('2026-09-14T00:00:00Z'),
                tenant: $tenant,
                originActor: new ActorRef('origin-a', 'customer'),
            ),
        );
        $this->connection->executeStatement(
            'UPDATE "' . $this->schema . '"."operations" SET state = :state WHERE operation_id = :operation_id',
            ['state' => LifecycleState::Completed->value, 'operation_id' => $operationId->toString()],
        );
    }

    private function record(
        OperationId $operationId,
        ?TenantRef $tenant,
        int $sequence,
        JournalEvent $event,
        \BlackOps\Journal\JournalData $data,
        ?\BlackOps\Journal\JournalAttempt $attempt = null,
    ): JournalRecord {
        $number = hexdec(substr($operationId->toString(), -4));
        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019f90ab-1000-7000-8000-%012x', ($number * 10) + $sequence)),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-09-14T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $operationId,
                self::OPERATION_TYPE,
                1,
                'deferred',
                CorrelationId::fromString(sprintf('019f90ab-1200-7000-8000-%012x', $number)),
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

    private function queryFailure(
        string $resource,
        string $code,
    ): OperationJournalQueryException|OperationOutcomeQueryException {
        if ($resource === 'journal') {
            return match ($code) {
                OperationJournalQueryException::AUTHORIZATION_FAILED
                    => OperationJournalQueryException::authorizationFailed(),
                OperationJournalQueryException::STORAGE_FAILED => OperationJournalQueryException::storageFailed(),
                OperationJournalQueryException::PROTECTION_FAILED => OperationJournalQueryException::protectionFailed(),
                OperationJournalQueryException::DECODE_FAILED => OperationJournalQueryException::decodeFailed(),
                OperationJournalQueryException::INTEGRITY_FAILED => OperationJournalQueryException::integrityFailed(),
            };
        }

        return match ($code) {
            OperationOutcomeQueryException::AUTHORIZATION_FAILED
                => OperationOutcomeQueryException::authorizationFailed(),
            OperationOutcomeQueryException::STORAGE_FAILED => OperationOutcomeQueryException::storageFailed(),
            OperationOutcomeQueryException::PROTECTION_FAILED => OperationOutcomeQueryException::protectionFailed(),
            OperationOutcomeQueryException::DECODE_FAILED => OperationOutcomeQueryException::decodeFailed(),
            OperationOutcomeQueryException::INTEGRITY_FAILED => OperationOutcomeQueryException::integrityFailed(),
        };
    }

    /** @return list<array{action_type:string,record_id:string,protected_body:string,tenant_type:string|null,tenant_id:string|null}> */
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
        /** @var list<array{action_type:string,record_id:string,protected_body:string,tenant_type:string|null,tenant_id:string|null}> $rows */
        $rows = $this->probe->fetchAllAssociative(
            'SELECT action_type, record_id::text AS record_id, encode(protected_body, \'hex\') AS protected_body, '
            . 'tenant_type, tenant_id FROM "'
            . $this->schema
            . '"."audit_records" '
            . 'WHERE resource_id = :resource_id AND '
            . $tenantWhere
            . ' ORDER BY recorded_at, record_id',
            $parameters,
        );

        return $rows;
    }

    /** @param list<string> $phases */
    private function assertAuditPhases(
        OperationId $operationId,
        ?TenantRef $tenant,
        string $resource,
        array $phases,
    ): void {
        $prefix = 'access.operation_' . $resource . '.';
        $rows = array_values(array_filter(
            $this->auditRows($operationId, $tenant),
            static fn(array $row): bool => str_starts_with($row['action_type'], $prefix),
        ));
        self::assertSame(
            array_map(static fn(string $phase): string => $prefix . $phase, $phases),
            array_column($rows, 'action_type'),
        );
        $this->assertProtectedAuditRowsContainNoOperationData($rows);
    }

    /** @param list<array{action_type:string,record_id:string,protected_body:string,tenant_type:string|null,tenant_id:string|null}> $rows */
    private function assertProtectedAuditRowsContainNoOperationData(array $rows): void
    {
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertStringStartsWith('424f5044', $row['protected_body']);
            foreach ([
                'operation-data-private-canary',
                'operation-payload-canary',
                'operation-context-canary',
                'application.view',
                'foreign-subject-private-canary',
                'named-private-canary',
                'global-private-canary',
                'denied-private-canary',
                'foreign-private-canary',
                'alternate-first-private-canary',
                'alternate-second-private-canary',
                'journal-iteration-private-canary',
                'trace-private-canary',
                'unexpected-reader-canary',
                'operation-value-canary',
                'terminal-private-canary',
            ] as $canary) {
                self::assertStringNotContainsString(bin2hex($canary), strtolower($row['protected_body']));
            }
        }
    }

    /** @param list<string> $canaries */
    private function assertAuditDraftsContainNoOperationData(array $canaries): void
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
            self::assertSame([], $draft->correlation->toArray());
            self::assertSame(['version' => 1], $draft->evidence->toArray());
            self::assertSame(['status', 'code'], array_keys($draft->outcome->toArray()));
            self::assertSame('operation', $draft->resource->type);
            self::assertSame('framework', $draft->source->owner->value);
            self::assertContains($draft->source->component, ['operation-journal', 'operation-outcome']);
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            self::assertIsString($encoded);
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString($canary, strtolower($encoded));
            }
            self::assertStringNotContainsString('purpose', strtolower($encoded));
        }
    }

    /** @param list<array{action_type:string,record_id:string,protected_body:string,tenant_type:string|null,tenant_id:string|null}> $rows */
    private function assertDistinctAuditIdentityOwners(array $rows): void
    {
        self::assertCount(count($this->auditWriter->requested), $this->auditWriter->terminals);
        self::assertCount(count($this->auditWriter->requested), $this->auditWriter->requestedBases);
        self::assertCount(count($this->auditWriter->terminals), $this->auditWriter->terminalContexts);
        $recordIds = array_column($rows, 'record_id');
        $executionIds = [];
        $nonces = [];
        foreach ($this->auditWriter->requestedBases as $index => $basis) {
            $executionIds[] = $basis->execution->id;
            $nonces[] = $basis->nonce;
            $expectedExecutionType = str_contains($this->auditWriter->requested[$index]->source->component, 'journal')
                ? 'operation_journal_read'
                : 'operation_outcome_read';
            self::assertSame($expectedExecutionType, $basis->execution->type);
            self::assertSame($basis->requestRef(), $this->auditWriter->requested[$index]->action->id);
            self::assertSame($basis->requestRef(), $this->auditWriter->terminalContexts[$index]->requestRef);
            self::assertSame($basis->nonce, $this->auditWriter->terminalContexts[$index]->nonce);
            self::assertNotContains($basis->execution->id, $recordIds);
            self::assertNotContains($basis->nonce, $recordIds);
        }
        self::assertCount(count($executionIds), array_unique($executionIds));
        self::assertCount(count($nonces), array_unique($nonces));
        self::assertCount(count($recordIds), array_unique($recordIds));
    }

    /** @param list<string> $canaries */
    private function assertStoredOperationPayloadsAreEncrypted(OperationId $operationId, array $canaries): void
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
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString(bin2hex($canary), strtolower($row[$key]));
            }
        }
    }

    private function requestedAuditCount(): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "'
            . $this->schema
            . '"."audit_records" '
            . "WHERE action_type IN ('access.operation_journal.requested', 'access.operation_outcome.requested')",
        );
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . $this->schema . '"."audit_outbox_records"');
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString('019f90ab-0100-7000-8000-' . sprintf('%012x', $number));
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

final class PostgreSqlD2JAuditDraftWriter implements AuditReadEventWriter
{
    /** @var list<AuditRecordDraft> */
    public array $requested = [];

    /** @var list<AuditRecordDraft> */
    public array $terminals = [];

    /** @var list<AuditReadRequestBasis> */
    public array $requestedBases = [];

    /** @var list<AuditReadContext> */
    public array $terminalContexts = [];

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
        $this->requestedBases[] = $basis;
        $this->delegate->requested($basis, $draft);
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        $this->terminals[] = $draft;
        $this->terminalContexts[] = $context;

        return $this->delegate->terminal($context, $draft);
    }
}

final class PostgreSqlD2JAuthorizer implements OperationDataReadAuthorizer
{
    public int $calls = 0;

    public function __construct(
        private readonly OperationDataReadAuthorizationDecision $decision,
        private readonly ?Closure $callback = null,
    ) {}

    public function decide(
        #[\SensitiveParameter]
        OperationDataReadAuthorizationRequest $request,
    ): OperationDataReadAuthorizationDecision {
        ++$this->calls;
        if ($this->callback !== null) {
            ($this->callback)($request);
        }

        return $this->decision;
    }
}

final readonly class PostgreSqlD2JFixedSubjectReader implements OperationDataSubjectReader
{
    public function __construct(
        private OperationDataSubject $subject,
    ) {}

    public function findSubject(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationDataSubject {
        return $this->subject;
    }
}

final class PostgreSqlD2JThrowingJournalReader implements TenantScopedCanonicalJournalReader
{
    public function __construct(
        private readonly Throwable $failure,
    ) {}

    public function recordsForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): iterable {
        throw $this->failure;
    }
}

final class PostgreSqlD2JThrowingOutcomeReader implements TenantScopedOutcomeReader
{
    public function __construct(
        private readonly Throwable $failure,
    ) {}

    public function findForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OutcomeRecord {
        throw $this->failure;
    }
}

final class PostgreSqlD2JUnexpectedJournalReader implements TenantScopedCanonicalJournalReader
{
    public function __construct(
        private readonly Throwable $failure,
    ) {}

    public function recordsForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): iterable {
        throw $this->failure;
    }
}

final class PostgreSqlD2JUnexpectedOutcomeReader implements TenantScopedOutcomeReader
{
    public function __construct(
        private readonly Throwable $failure,
    ) {}

    public function findForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OutcomeRecord {
        throw $this->failure;
    }
}

final class ThrowableForPostgreSqlD2J extends \RuntimeException {}

final class PostgreSqlD2JStorageKeyProvider implements StorageKeyProvider
{
    public int $calls = 0;

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        ++$this->calls;

        return $this->key('test-key', $tenant, $purpose);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
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

final class PostgreSqlD2JAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return $this->key('audit-key', $tenant, $purpose);
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        if ($keyId !== 'audit-key') {
            throw new \InvalidArgumentException('Unknown Audit key.');
        }

        return new StorageKey($keyId, str_repeat('a', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }
}

final class PostgreSqlD2JAuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'audit-d2j-nonce-' . $this->calls, binary: true), 0, 24);
    }
}

final class PostgreSqlD2JAuditClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-14T00:00:10.000000Z');
    }
}

final class PostgreSqlD2JAuditUuidGenerator implements AuditUuidv7Generator
{
    private int $calls = 0;

    public function generate(DateTimeImmutable $time): string
    {
        ++$this->calls;

        return sprintf('019f90ab-2000-7000-8000-%012x', $this->calls);
    }
}

final class PostgreSqlD2JReadNonceSource implements OperationReadNonceSource
{
    public int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'read-d2j-nonce-' . $this->calls, binary: true), 0, 16);
    }
}

final class PostgreSqlD2JReadClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-14T00:00:11.000000Z');
    }
}

final class PostgreSqlD2JExecutionUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    public function __construct(
        private readonly string $prefix,
    ) {}

    public function generate(): string
    {
        ++$this->calls;

        return '019f90ab-' . $this->prefix . '-7000-8000-' . sprintf('%012x', $this->calls);
    }
}

final class PostgreSqlD2JConnection extends Connection
{
    /** @var list<int> */
    public array $requestedVisibleBeforeSubject = [];

    /** @var list<string> */
    public array $transactionIsolations = [];

    /** @var list<string> */
    public array $transactionReadOnlyStates = [];

    public int $beginAttempts = 0;

    public int $commitAttempts = 0;

    public int $rollbackAttempts = 0;

    public int $subjectReadAttempts = 0;

    public int $auditAppendAttempts = 0;

    public int $protectedJournalReadAttempts = 0;

    public int $protectedOutcomeReadAttempts = 0;

    public int $readOnlyCommits = 0;

    public bool $readFailure = false;

    public ?string $auditAppendFailure = null;

    public ?string $terminalCommitFailure = null;

    private bool $invalidPostState = false;

    public function __construct(
        array $params,
        Driver $driver,
        private readonly string $schema,
        private readonly Connection $probe,
    ) {
        parent::__construct($params, $driver);
    }

    public function beginTransaction(): void
    {
        ++$this->beginAttempts;
        parent::beginTransaction();
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        if (str_contains($query, 'outcome.outcome_type') && str_contains($query, 'encoded_payload')) {
            ++$this->protectedOutcomeReadAttempts;
        }
        if ($this->isSubjectQuery($query)) {
            ++$this->subjectReadAttempts;
            $auditRecords = new PostgreSqlAuditSchema($this->schema)->recordsTable();
            $this->requestedVisibleBeforeSubject[] = (int) $this->probe->fetchOne(
                "SELECT count(*) FROM {$auditRecords} WHERE action_type IN "
                . "('access.operation_journal.requested', 'access.operation_outcome.requested')",
            );
            if ($this->readFailure) {
                throw new \RuntimeException('subject-read-canary');
            }
        }

        return parent::fetchAssociative($query, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        if (str_contains($query, 'record_id::text') && str_contains($query, 'encoded_record')) {
            ++$this->protectedJournalReadAttempts;
        }

        return parent::iterateAssociative($query, $params, $types);
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
                throw new \RuntimeException('audit-append-canary');
            }
        }

        return $value;
    }

    public function commit(): void
    {
        ++$this->commitAttempts;
        $readOnly = (string) $this->fetchOne('SHOW transaction_read_only');
        if ($readOnly === 'on') {
            ++$this->readOnlyCommits;
        }
        if ($this->terminalCommitFailure === 'before' && $this->auditAppendAttempts === 2) {
            throw new \RuntimeException('terminal-commit-before-canary');
        }
        parent::commit();
        if ($this->terminalCommitFailure === 'after' && $this->auditAppendAttempts === 2) {
            throw new \RuntimeException('terminal-commit-after-canary');
        }
        if ($this->terminalCommitFailure === 'poststate' && $this->auditAppendAttempts === 2) {
            $this->invalidPostState = true;
        }
    }

    public function rollBack(): void
    {
        ++$this->rollbackAttempts;
        parent::rollBack();
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->invalidPostState ? 1 : parent::getTransactionNestingLevel();
    }

    private function isSubjectQuery(string $query): bool
    {
        $schema = '"' . $this->schema . '"';

        return (
            str_contains($query, $schema . '."operations"') && str_contains($query, 'origin_actor_type')
            || str_contains($query, $schema . '."journal"')
            && str_contains($query, 'origin_actor_type')
            && str_contains($query, 'LIMIT 1')
        );
    }
}

final readonly class PostgreSqlD2JValue implements \BlackOps\Core\OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class PostgreSqlD2JOutcome implements Outcome
{
    public function __construct(
        public string $status,
        public string $private,
    ) {}
}
