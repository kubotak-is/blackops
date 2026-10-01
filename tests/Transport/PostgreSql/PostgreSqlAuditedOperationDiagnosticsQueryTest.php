<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Sensitive;
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
use BlackOps\Internal\Diagnostics\AuditedOperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\DiagnosticsAvailability;
use BlackOps\Internal\Diagnostics\DiagnosticsFailureCode;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizationRequest;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizer;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsReadExecutionSource;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Internal\Diagnostics\PostgreSqlDiagnosticsSourceReader;
use BlackOps\Internal\Identifier\Uuidv7Generator as AuditUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReader;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Throwable;
use Traversable;

/** @mago-expect lint:too-many-methods */
/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
final class PostgreSqlAuditedOperationDiagnosticsQueryTest extends TestCase
{
    private const string OPERATION_TYPE = 'diagnostics.audited.postgresql';

    private const string INLINE_TYPE = 'diagnostics.audited.inline';

    private const string SCHEMA_PREFIX = 'blackops_p23_d2x_';

    private const string AUDIT_KEY = '0123456789abcdef0123456789abcdef';

    private string $schema;

    private Connection $admin;

    private Connection $probe;

    private PostgreSqlD2XConnection $connection;

    private PostgreSqlDeferredOperationSender $sender;

    private PostgreSqlCanonicalJournalStore $journal;

    private PostgreSqlOutcomeStore $outcomes;

    private BopdEnvelopeCodec $operationProtection;

    private OperationReadAuditSessionFactory $sessions;

    private PostgreSqlD2XAuditDraftWriter $auditWriter;

    private HmacAuditOpaqueIdDeriver $opaqueIds;

    private PostgreSqlD2XReadNonceSource $readNonces;

    private PostgreSqlD2XExecutionUuidGenerator $diagnosticsExecution;

    protected function setUp(): void
    {
        $this->schema = self::SCHEMA_PREFIX . bin2hex(random_bytes(6));
        $this->admin = $this->connection();
        $this->probe = $this->connection();
        $base = $this->connection();
        $params = $base->getParams();
        $driver = $base->getDriver();
        $base->close();
        $this->connection = new PostgreSqlD2XConnection($params, $driver, $this->schema, $this->probe);
        $this->admin->executeStatement('CREATE SCHEMA "' . $this->schema . '"');

        $this->operationProtection = PostgreSqlTestStorageProtection::codec();
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
        $this->sessions = $this->auditSessions($recordSchema, $outboxSchema);
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

    public function testTrustedGlobalScopeReadsInlineAndDeferredTenantOwnedOperations(): void
    {
        $globalId = $this->id(1);
        $tenantId = $this->id(2);
        $tenant = $this->tenant('tenant-owned');
        $actor = new ActorRef('diagnostics-reader-private-canary', 'operator');
        $this->createInlineCompleted($globalId, null);
        $this->createDeferredCompleted($tenantId, $tenant);
        $requestedCounts = [];
        $authorizer =
            new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow, function (OperationDiagnosticsAuthorizationRequest $request) use (
                &$requestedCounts,
                $actor,
            ): void {
                $requestedCounts[] = $this->requestedAuditCount();
                self::assertSame($actor->id(), $request->currentActor()?->id());
                self::assertSame($actor->type(), $request->currentActor()?->type());
                self::assertNull($request->currentTenant());
            });

        $inline = $this->query($authorizer)->find($globalId, $actor, null);
        $deferred = $this->query($authorizer)->find($tenantId, $actor, null);

        self::assertInstanceOf(OperationDiagnosticsFound::class, $inline);
        self::assertInstanceOf(OperationDiagnosticsFound::class, $deferred);
        $this->assertSafeFound($inline, 'inline', 'journal');
        $this->assertSafeFound($deferred, 'deferred', 'outcome_store');
        self::assertSame([1, 2], $requestedCounts);
        self::assertSame(2, $authorizer->calls);
        self::assertCount(9, $this->connection->requestedVisibleBeforeDiagnosticsRead);
        self::assertGreaterThanOrEqual(1, min($this->connection->requestedVisibleBeforeDiagnosticsRead));
        self::assertSame(array_fill(0, 9, true), $this->connection->diagnosticsReadAutoCommit);
        self::assertSame(array_fill(0, 9, false), $this->connection->diagnosticsReadTransactions);
        self::assertSame(9, $this->connection->journalRowsConsumed);
        self::assertSame([1, 2], $this->auditWriter->requestedVisibleCounts);
        self::assertSame([1, 2], $this->auditWriter->terminalVisibleCounts);
        self::assertSame(4, $this->connection->auditAppendAttempts);
        self::assertSame(4, $this->connection->commitAttempts);
        self::assertSame(0, $this->connection->rollbackAttempts);
        self::assertNotContains('on', $this->connection->transactionReadOnlyStates);
        self::assertNotContains('repeatable read', $this->connection->transactionIsolations);
        self::assertSame(2, $this->diagnosticsExecution->calls);
        self::assertSame(2, $this->readNonces->calls);
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());

        $globalRows = $this->auditRows($globalId, null);
        $tenantRows = $this->auditRows($tenantId, null);
        $this->assertPhases($globalId, null, ['requested', 'accessed']);
        $this->assertPhases($tenantId, null, ['requested', 'accessed']);
        $this->assertDistinctAuditIdentityOwners([...$globalRows, ...$tenantRows]);
        $this->assertAuditDraftsContainNoOperationData([
            'diagnostics-origin-private-canary',
            'diagnostics-authorization-private-canary',
            'diagnostics-execution-private-canary',
            'diagnostics-payload-private-canary',
            'diagnostics-context-private-canary',
            'diagnostics-outcome-private-canary',
        ]);
        $this->assertProtectedRowsContainNoCanaries([
            ...$this->auditRows($globalId, null),
            ...$this->auditRows($tenantId, null),
        ], [
            'diagnostics-reader-private-canary',
            'diagnostics-origin-private-canary',
            'diagnostics-authorization-private-canary',
            'diagnostics-execution-private-canary',
            'diagnostics-payload-private-canary',
            'diagnostics-context-private-canary',
            'diagnostics-outcome-private-canary',
        ]);
        $this->assertInlinePayloadIsEncrypted($globalId);
        $this->assertDeferredPayloadsAreEncrypted($tenantId, $tenant);
        self::assertSame(0, $this->outboxCount());
    }

    public function testDeniedDefaultAndNonNullTenantAreUnavailableWithoutReaderWork(): void
    {
        $deniedId = $this->id(3);
        $defaultId = $this->id(4);
        $nonNullId = $this->id(5);
        $tenant = $this->tenant('requester-tenant');
        $this->createDeferredCompleted($deniedId, $tenant);
        $this->createDeferredCompleted($defaultId, $tenant);
        $this->createDeferredCompleted($nonNullId, $tenant);

        $deny = new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Deny);
        $denied = $this->query($deny)->find($deniedId, null, null);
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $denied);
        self::assertSame(1, $deny->calls);
        self::assertSame(0, $this->connection->diagnosticsReadAttempts);

        $default = $this->query(new \BlackOps\Internal\Diagnostics\UnavailableOperationDiagnosticsAuthorizer())->find(
            $defaultId,
            null,
            null,
        );
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $default);
        self::assertSame(0, $this->connection->diagnosticsReadAttempts);

        $nonNullAllow = new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow);
        $tenantResult = $this->query($nonNullAllow)->find(
            $nonNullId,
            new ActorRef('tenant-reader', 'operator'),
            $tenant,
        );
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $tenantResult);
        self::assertSame(0, $nonNullAllow->calls);
        self::assertSame(0, $this->connection->diagnosticsReadAttempts);
        self::assertFalse($this->connection->isTransactionActive());

        $this->assertPhases($deniedId, null, ['requested', 'unavailable']);
        $this->assertPhases($defaultId, null, ['requested', 'unavailable']);
        $this->assertPhases($nonNullId, $tenant, ['requested', 'unavailable']);
        self::assertSame([1, 2, 3], $this->auditWriter->terminalVisibleCounts);
        self::assertSame(0, $this->outboxCount());
    }

    public function testMissingPurgedAndDanglingDeadLetterPreserveDiagnosticsSemantics(): void
    {
        $partialId = $this->id(10);
        $fullyPurgedId = $this->id(11);
        $missingId = $this->id(12);
        $missingPurgedId = $this->id(13);
        $danglingId = $this->id(14);
        $this->createDeferredCompleted($partialId, null);
        $this->createDeferredCompleted($fullyPurgedId, null);
        $this->purgeJournalOnly($partialId, 1);
        $this->purgeEverything($fullyPurgedId, 2);
        $this->insertPurgeAudit($missingPurgedId, RetentionPurgeTarget::Journal, 5);
        $this->insertDeadLetter($danglingId, 4);
        self::assertSame(0, $this->countOperationRows($danglingId));
        self::assertSame(0, $this->countJournalRows($danglingId));
        self::assertSame(1, $this->countDeadLetterRows($danglingId));
        $authorizer = new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow);

        $partial = $this->query($authorizer)->find($partialId, null, null);
        $fullyPurged = $this->query($authorizer)->find($fullyPurgedId, null, null);
        $missing = $this->query($authorizer)->find($missingId, null, null);
        $missingPurged = $this->query($authorizer)->find($missingPurgedId, null, null);

        self::assertInstanceOf(OperationDiagnosticsFound::class, $partial);
        self::assertSame(DiagnosticsAvailability::Purged, $partial->diagnostics->availability->journal);
        self::assertSame(DiagnosticsAvailability::Available, $partial->diagnostics->availability->outcome);
        self::assertInstanceOf(OperationDiagnosticsFound::class, $fullyPurged);
        self::assertSame(DiagnosticsAvailability::Purged, $fullyPurged->diagnostics->availability->transportPayload);
        self::assertSame(DiagnosticsAvailability::Purged, $fullyPurged->diagnostics->availability->journal);
        self::assertSame(DiagnosticsAvailability::Purged, $fullyPurged->diagnostics->availability->outcome);
        self::assertSame([], $fullyPurged->diagnostics->timeline);
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $missing);
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $missingPurged);

        try {
            $this->query($authorizer)->find($danglingId, null, null);
            self::fail('Dangling dead-letter evidence was accepted.');
        } catch (OperationDiagnosticsException $exception) {
            $this->assertFreshFailure($exception, DiagnosticsFailureCode::IntegrityFailed);
        }

        self::assertSame(5, $authorizer->calls);
        foreach ([$missingId, $missingPurgedId, $danglingId] as $id) {
            $this->assertPhases($id, null, ['requested', 'unavailable']);
        }
        $this->assertPhases($partialId, null, ['requested', 'accessed']);
        $this->assertPhases($fullyPurgedId, null, ['requested', 'accessed']);
        self::assertSame(5, count($this->auditWriter->terminalVisibleCounts));
        self::assertSame(0, $this->outboxCount());
    }

    public function testStorageDecodeAndIntegrityFailuresAreReconstructedAfterUnavailableTerminal(): void
    {
        $decodeId = $this->id(20);
        $integrityId = $this->id(21);
        $storageId = $this->id(22);
        $this->createDeferredCompleted($decodeId, null);
        $this->createDeferredCompleted($integrityId, null);
        $this->createDeferredCompleted($storageId, null);
        $this->corruptJournalRecord($decodeId, 2);
        $this->insertDeadLetter($integrityId, 20);
        $authorizer = new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow);

        try {
            $this->query($authorizer)->find($decodeId, null, null);
            self::fail('Journal decode failure was swallowed.');
        } catch (OperationDiagnosticsException $exception) {
            $this->assertFreshFailure($exception, DiagnosticsFailureCode::DecodeFailed);
        }
        try {
            $this->query($authorizer)->find($integrityId, null, null);
            self::fail('Diagnostics integrity failure was swallowed.');
        } catch (OperationDiagnosticsException $exception) {
            $this->assertFreshFailure($exception, DiagnosticsFailureCode::IntegrityFailed);
        }

        $this->probe->executeStatement('DROP TABLE "' . $this->schema . '"."operations" CASCADE');
        try {
            $this->query($authorizer)->find($storageId, null, null);
            self::fail('Diagnostics storage failure was swallowed.');
        } catch (OperationDiagnosticsException $exception) {
            $this->assertFreshFailure($exception, DiagnosticsFailureCode::StorageFailed);
        }

        self::assertSame(3, $authorizer->calls);
        $this->assertPhases($decodeId, null, ['requested', 'unavailable']);
        $this->assertPhases($integrityId, null, ['requested', 'unavailable']);
        $this->assertPhases($storageId, null, ['requested', 'unavailable']);
        self::assertSame(3, count($this->auditWriter->terminalVisibleCounts));
        self::assertSame(0, $this->outboxCount());
    }

    public function testTerminalCommitFailureWinsWithoutRetryAndLeavesCommittedPrefix(): void
    {
        $operationId = $this->id(30);
        $this->createDeferredCompleted($operationId, null);
        $this->connection->terminalCommitFailure = 'after';

        try {
            $this->query(new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow))->find(
                $operationId,
                null,
                null,
            );
            self::fail('Terminal commit failure was swallowed.');
        } catch (OperationDiagnosticsException $exception) {
            $this->assertFreshFailure($exception, DiagnosticsFailureCode::StorageFailed);
        }

        $this->assertPhases($operationId, null, ['requested', 'accessed']);
        self::assertSame(2, $this->connection->auditAppendAttempts);
        self::assertSame(2, $this->connection->commitAttempts);
        self::assertSame(1, count($this->auditWriter->terminals));
        self::assertSame([], $this->auditWriter->terminalVisibleCounts);
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->outboxCount());
    }

    public function testReaderFailureTraceAndAuditBodiesHideProtectedCanaries(): void
    {
        $operationId = $this->id(40);
        $this->createDeferredCompleted($operationId, null);
        $this->connection->journalFailure = 'journal-reader-private-canary';
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                $this->query(new PostgreSqlD2XAuthorizer(AuditReadAuthorizationDecision::Allow))->find(
                    $operationId,
                    new ActorRef('trace-reader-private-canary', 'operator'),
                    null,
                );
                self::fail('Reader failure was swallowed.');
            } catch (OperationDiagnosticsException $exception) {
                $this->assertFreshFailure($exception, DiagnosticsFailureCode::DecodeFailed);
                $traceFrames = array_filter($exception->getTrace(), static fn(array $frame): bool => str_starts_with(
                    (string) ($frame['class'] ?? ''),
                    'BlackOps\\Internal\\',
                ));
                self::assertNotEmpty($traceFrames);
                $protectedArguments = [];
                foreach ($traceFrames as $frame) {
                    foreach ($frame['args'] ?? [] as $argument) {
                        $protectedArguments[] = $argument;
                    }
                }
                self::assertNotEmpty($protectedArguments);
                foreach ($protectedArguments as $argument) {
                    self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
                }
                $frameworkTrace = print_r($traceFrames, true) . var_export($traceFrames, true);
                self::assertStringContainsString('SensitiveParameterValue', $frameworkTrace);
                $representations = [
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                    print_r($exception->getTrace(), true),
                    print_r($exception, true),
                    var_export($exception, true),
                    json_encode($exception, JSON_THROW_ON_ERROR),
                ];
                try {
                    $representations[] = serialize($exception);
                } catch (Throwable $serializationFailure) {
                    $representations[] = $serializationFailure->getMessage();
                    self::assertNull($serializationFailure->getPrevious());
                }
                foreach ([
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                    $frameworkTrace,
                ] as $representation) {
                    foreach ([
                        $operationId->toString(),
                        'journal-reader-private-canary',
                        'trace-reader-private-canary',
                    ] as $canary) {
                        self::assertStringNotContainsString($canary, $representation);
                    }
                }
                foreach ($representations as $representation) {
                    self::assertIsString($representation);
                }
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }

        $this->assertPhases($operationId, null, ['requested', 'unavailable']);
        $this->assertProtectedRowsContainNoCanaries(
            $this->auditRows($operationId, null),
            [
                'journal-reader-private-canary',
                'trace-reader-private-canary',
                'diagnostics-payload-private-canary',
                'diagnostics-outcome-private-canary',
            ],
        );
        $this->assertAuditDraftsContainNoOperationData([
            'journal-reader-private-canary',
            'diagnostics-payload-private-canary',
            'diagnostics-outcome-private-canary',
        ]);
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
            new PostgreSqlD2XAuditKeySource(),
            new PostgreSqlD2XAuditNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new PostgreSqlD2XAuditClock();
        $uuids = new PostgreSqlD2XAuditUuidGenerator();
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
        $this->auditWriter = new PostgreSqlD2XAuditDraftWriter(
            new AuditProtectedReadGate($delivery),
            $this->probe,
            $this->schema,
        );
        $this->readNonces = new PostgreSqlD2XReadNonceSource();
        $this->diagnosticsExecution = new PostgreSqlD2XExecutionUuidGenerator();

        return new OperationReadAuditSessionFactory(
            $this->auditWriter,
            $this->opaqueIds,
            $this->readNonces,
            new PostgreSqlD2XReadClock(),
            new OperationDiagnosticsReadExecutionSource($this->diagnosticsExecution),
            AuditSourceRuntime::Http,
            'postgresql-d2x-test',
        );
    }

    private function query(OperationDiagnosticsAuthorizer $authorizer): AuditedOperationDiagnosticsQuery
    {
        return new AuditedOperationDiagnosticsQuery(
            $this->journal,
            $this->outcomes,
            new PostgreSqlDiagnosticsSourceReader(new PostgreSqlDiagnosticsReader($this->connection, $this->schema)),
            $authorizer,
            $this->sessions,
            $this->connection,
        );
    }

    private function createDeferredCompleted(OperationId $operationId, ?TenantRef $tenant): void
    {
        $this->sender->enqueue(
            new DeferredOperationMessage(
                $operationId,
                self::OPERATION_TYPE,
                1,
                '{"diagnostics_payload":"diagnostics-payload-private-canary"}',
                '{"diagnostics_context":"diagnostics-context-private-canary"}',
                new DateTimeImmutable('2026-09-15T00:00:00Z'),
                tenant: $tenant,
                originActor: new ActorRef('diagnostics-origin-private-canary', 'customer'),
            ),
        );
        $attempt = $this->attempt($operationId);
        foreach ([
            $this->record(
                $operationId,
                $tenant,
                self::OPERATION_TYPE,
                'deferred',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new PostgreSqlD2XValue('visible', 'diagnostics-credential-private-canary')),
            ),
            $this->record($operationId, $tenant, self::OPERATION_TYPE, 'deferred', 2, JournalEvent::OperationAccepted),
            $this->record(
                $operationId,
                $tenant,
                self::OPERATION_TYPE,
                'deferred',
                3,
                JournalEvent::AttemptStarted,
                attempt: $attempt,
            ),
            $this->record(
                $operationId,
                $tenant,
                self::OPERATION_TYPE,
                'deferred',
                4,
                JournalEvent::AttemptSucceeded,
                attempt: $attempt,
            ),
            $this->record(
                $operationId,
                $tenant,
                self::OPERATION_TYPE,
                'deferred',
                5,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new PostgreSqlD2XOutcome('ready', 'diagnostics-outcome-private-canary')),
                $attempt,
            ),
        ] as $record) {
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
                new PostgreSqlD2XOutcome('ready', 'diagnostics-outcome-private-canary'),
                new DateTimeImmutable('2026-09-15T00:00:06Z'),
            ),
        );
    }

    private function createInlineCompleted(OperationId $operationId, ?TenantRef $tenant): void
    {
        $attempt = $this->attempt($operationId);
        foreach ([
            $this->record(
                $operationId,
                $tenant,
                self::INLINE_TYPE,
                'inline',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new PostgreSqlD2XValue('visible', 'diagnostics-credential-private-canary')),
            ),
            $this->record(
                $operationId,
                $tenant,
                self::INLINE_TYPE,
                'inline',
                2,
                JournalEvent::AttemptStarted,
                attempt: $attempt,
            ),
            $this->record(
                $operationId,
                $tenant,
                self::INLINE_TYPE,
                'inline',
                3,
                JournalEvent::AttemptSucceeded,
                attempt: $attempt,
            ),
            $this->record(
                $operationId,
                $tenant,
                self::INLINE_TYPE,
                'inline',
                4,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new PostgreSqlD2XOutcome('ready', 'diagnostics-outcome-private-canary')),
                $attempt,
            ),
        ] as $record) {
            $this->journal->append($record);
        }
    }

    private function record(
        OperationId $operationId,
        ?TenantRef $tenant,
        string $type,
        string $strategy,
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        ?JournalAttempt $attempt = null,
    ): JournalRecord {
        $number = hexdec(substr($operationId->toString(), -6));

        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019fa2ab-1300-7000-8000-%012x', ($number * 10) + $sequence)),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-09-15T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $operationId,
                $type,
                1,
                $strategy,
                CorrelationId::fromString('019fa2ab-1200-7000-8000-' . substr($operationId->toString(), -12)),
                actorContext: new ActorContext(
                    new ActorRef('diagnostics-origin-private-canary', 'customer'),
                    new ActorRef('diagnostics-authorization-private-canary', 'customer'),
                    new ActorRef('diagnostics-execution-private-canary', 'worker'),
                ),
                tenant: $tenant,
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    private function attempt(OperationId $operationId): JournalAttempt
    {
        return new JournalAttempt(
            AttemptId::fromString('019fa2ab-1100-7000-8000-' . substr($operationId->toString(), -12)),
            1,
            new DateTimeImmutable('2026-09-15T00:00:03Z'),
        );
    }

    private function purgeJournalOnly(OperationId $operationId, int $number): void
    {
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."journal" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->insertPurgeAudit($operationId, RetentionPurgeTarget::Journal, $number);
    }

    private function purgeEverything(OperationId $operationId, int $number): void
    {
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."outcomes" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->probe->executeStatement(
            'DELETE FROM "' . $this->schema . '"."journal" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
        $this->probe->executeStatement('UPDATE "' . $this->schema . '"."operations"
             SET encoded_payload = NULL, encoded_context = NULL, payload_purged_at = :purged_at
             WHERE operation_id = :operation_id', [
            'purged_at' => '2026-09-15T01:00:00Z',
            'operation_id' => $operationId->toString(),
        ]);
        foreach ([
            RetentionPurgeTarget::TransportPayload,
            RetentionPurgeTarget::Journal,
            RetentionPurgeTarget::Outcome,
        ] as $offset => $target) {
            $this->insertPurgeAudit($operationId, $target, $number + $offset);
        }
    }

    private function insertPurgeAudit(OperationId $operationId, RetentionPurgeTarget $target, int $number): void
    {
        $this->probe->executeStatement(
            'INSERT INTO "'
            . $this->schema
            . '"."retention_purge_audits" (
                audit_id, operation_id, target, affected_count, policy, purged_at, purged_by
             ) VALUES (:audit_id, :operation_id, :target, 1, :policy, :purged_at, :purged_by)',
            [
                'audit_id' => sprintf('019fa2ab-1400-7000-8000-%012x', $number),
                'operation_id' => $operationId->toString(),
                'target' => $target->value,
                'policy' => 'diagnostics-retention-test',
                'purged_at' => sprintf('2026-09-15T01:00:%02dZ', $number % 60),
                'purged_by' => 'diagnostics-retention-test',
            ],
        );
    }

    private function insertDeadLetter(OperationId $operationId, int $number): void
    {
        $this->probe->executeStatement(
            'INSERT INTO "'
            . $this->schema
            . '"."dead_letters" (
                operation_id, final_attempt_id, final_attempt_number, encoded_reason, moved_at
             ) VALUES (:operation_id, :attempt_id, 1, decode(\'424f5044\', \'hex\'), :moved_at)',
            [
                'operation_id' => $operationId->toString(),
                'attempt_id' => $this->attempt($operationId)->id->toString(),
                'moved_at' => sprintf('2026-09-15T02:00:%02dZ', $number % 60),
            ],
        );
    }

    private function countOperationRows(OperationId $operationId): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "' . $this->schema . '"."operations" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
    }

    private function countJournalRows(OperationId $operationId): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "' . $this->schema . '"."journal" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
    }

    private function countDeadLetterRows(OperationId $operationId): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "' . $this->schema . '"."dead_letters" WHERE operation_id = :operation_id',
            ['operation_id' => $operationId->toString()],
        );
    }

    private function corruptJournalRecord(OperationId $operationId, int $sequence): void
    {
        $this->probe->executeStatement(
            'UPDATE "' . $this->schema . '"."journal"
             SET encoded_record = decode(\'424f5044\', \'hex\')
             WHERE operation_id = :operation_id AND sequence = :sequence',
            ['operation_id' => $operationId->toString(), 'sequence' => $sequence],
        );
    }

    private function assertSafeFound(OperationDiagnosticsResult $result, string $strategy, string $outcomeSource): void
    {
        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame($strategy, $result->diagnostics->identity->strategy);
        self::assertSame('customer', $result->diagnostics->identity->actors?->origin?->type);
        self::assertSame('[masked]', $result->diagnostics->identity->actors?->origin?->id);
        self::assertNotEmpty($result->diagnostics->timeline);
        self::assertSame('visible', $result->diagnostics->timeline[0]->data['message'] ?? null);
        self::assertArrayNotHasKey('credential', $result->diagnostics->timeline[0]->data);
        self::assertNotNull($result->diagnostics->outcome);
        self::assertSame($outcomeSource, $result->diagnostics->outcome->source);
        self::assertSame('ready', $result->diagnostics->outcome->data['status'] ?? null);
        self::assertArrayNotHasKey('secret', $result->diagnostics->outcome->data);
    }

    private function assertFreshFailure(OperationDiagnosticsException $exception, DiagnosticsFailureCode $code): void
    {
        self::assertSame(OperationDiagnosticsException::class, $exception::class);
        self::assertSame($code, $exception->diagnosticsCode);
        self::assertSame($code->value, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
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
            self::assertSame('diagnostics', $basis->purpose->value);
            self::assertSame('operation_diagnostics_read', $basis->execution->type);
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

    /** @param list<string> $phases */
    private function assertPhases(OperationId $operationId, ?TenantRef $tenant, array $phases): void
    {
        self::assertSame(
            array_map(static fn(string $phase): string => 'access.operation_diagnostics.' . $phase, $phases),
            array_column($this->auditRows($operationId, $tenant), 'action_type'),
        );
    }

    /**
     * @param list<array{action_type:string,record_id:string,protected_body:string,tenant_type:string|null,tenant_id:string|null}> $rows
     * @param list<string> $canaries
     */
    private function assertProtectedRowsContainNoCanaries(array $rows, array $canaries): void
    {
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertStringStartsWith('424f5044', $row['protected_body']);
            foreach ($canaries as $canary) {
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
            self::assertSame('operation-diagnostics', $draft->source->component);
            self::assertSame([], $draft->correlation->toArray());
            self::assertSame(['version' => 1], $draft->evidence->toArray());
            self::assertSame(['status', 'code'], array_keys($draft->outcome->toArray()));
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            self::assertIsString($encoded);
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString(strtolower($canary), strtolower($encoded));
            }
        }
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
        $rows = $this->probe->fetchAllAssociative('SELECT action_type, record_id::text AS record_id,
                    encode(protected_body, \'hex\') AS protected_body,
                    tenant_type, tenant_id
             FROM "'
        . $this->schema
        . '"."audit_records"
             WHERE resource_id = :resource_id AND '
        . $tenantWhere
        . '
             ORDER BY recorded_at, record_id', $parameters);

        return $rows;
    }

    private function requestedAuditCount(): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "'
            . $this->schema
            . '"."audit_records"
             WHERE action_type = \'access.operation_diagnostics.requested\'',
        );
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . $this->schema . '"."audit_outbox_records"');
    }

    private function assertInlinePayloadIsEncrypted(OperationId $operationId): void
    {
        $rows = $this->probe->fetchAllAssociative(
            'SELECT encode(encoded_record, \'hex\') AS payload
             FROM "' . $this->schema . '"."journal"
             WHERE operation_id = :operation_id
             ORDER BY sequence',
            ['operation_id' => $operationId->toString()],
        );
        self::assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertEncryptedBlob($row['payload'] ?? null);
        }
    }

    private function assertDeferredPayloadsAreEncrypted(OperationId $operationId, TenantRef $tenant): void
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
               AND o.tenant_type = :tenant_type AND o.tenant_id = :tenant_id
             ORDER BY j.sequence
             LIMIT 1',
            [
                'operation_id' => $operationId->toString(),
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
            ],
        );
        self::assertIsArray($row);
        foreach (['operation_payload', 'operation_context', 'journal_payload', 'outcome_payload'] as $key) {
            $this->assertEncryptedBlob($row[$key] ?? null);
        }
    }

    private function assertEncryptedBlob(mixed $payload): void
    {
        self::assertIsString($payload);
        self::assertStringStartsWith('424f5044', $payload);
        foreach ([
            'diagnostics-payload-private-canary',
            'diagnostics-context-private-canary',
            'diagnostics-credential-private-canary',
            'diagnostics-outcome-private-canary',
        ] as $canary) {
            self::assertStringNotContainsString(bin2hex($canary), strtolower($payload));
        }
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString(sprintf('019fa2ab-1000-7000-8000-%012x', $number));
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
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }
}

final class PostgreSqlD2XAuditDraftWriter implements AuditReadEventWriter
{
    /** @var list<AuditRecordDraft> */
    public array $requested = [];

    /** @var list<AuditRecordDraft> */
    public array $terminals = [];

    /** @var list<AuditReadRequestBasis> */
    public array $requestedBases = [];

    /** @var list<AuditReadContext> */
    public array $terminalContexts = [];

    /** @var list<int> */
    public array $requestedVisibleCounts = [];

    /** @var list<int> */
    public array $terminalVisibleCounts = [];

    public function __construct(
        private readonly AuditReadEventWriter $delegate,
        private readonly Connection $probe,
        private readonly string $schema,
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
        $this->requestedVisibleCounts[] = $this->count("action_type = 'access.operation_diagnostics.requested'");
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        $this->terminals[] = $draft;
        $this->terminalContexts[] = $context;
        $decision = $this->delegate->terminal($context, $draft);
        $this->terminalVisibleCounts[] = $this->count(
            "action_type IN ('access.operation_diagnostics.accessed', 'access.operation_diagnostics.unavailable')",
        );

        return $decision;
    }

    private function count(string $predicate): int
    {
        return (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "' . $this->schema . '"."audit_records" WHERE ' . $predicate,
        );
    }
}

final class PostgreSqlD2XAuthorizer implements OperationDiagnosticsAuthorizer
{
    public int $calls = 0;

    public function __construct(
        private readonly AuditReadAuthorizationDecision $decision,
        private readonly ?Closure $callback = null,
    ) {}

    public function decide(
        #[\SensitiveParameter]
        OperationDiagnosticsAuthorizationRequest $request,
    ): AuditReadAuthorizationDecision {
        ++$this->calls;
        if ($this->callback !== null) {
            ($this->callback)($request);
        }

        return $this->decision;
    }
}

final class PostgreSqlD2XAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        return $this->key('audit-key', $tenant, $purpose);
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        if ($keyId !== 'audit-key') {
            throw new \InvalidArgumentException('Unknown audit key.');
        }

        return new \BlackOps\StorageProtection\StorageKey($keyId, str_repeat(
            'a',
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
        ));
    }
}

final class PostgreSqlD2XAuditNonceSource implements NonceSource
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'audit-d2x-nonce-' . $this->calls, binary: true), 0, 24);
    }
}

final class PostgreSqlD2XAuditClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-15T00:00:10.000000Z');
    }
}

final class PostgreSqlD2XAuditUuidGenerator implements AuditUuidv7Generator
{
    private int $calls = 0;

    public function generate(DateTimeImmutable $time): string
    {
        ++$this->calls;

        return sprintf('019fa2ab-2000-7000-8000-%012x', $this->calls);
    }
}

final class PostgreSqlD2XReadNonceSource implements OperationReadNonceSource
{
    public int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'read-d2x-nonce-' . $this->calls, binary: true), 0, 16);
    }
}

final class PostgreSqlD2XReadClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-15T00:00:11.000000Z');
    }
}

final class PostgreSqlD2XExecutionUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return sprintf('019fa2ab-3000-7000-8000-%012x', $this->calls);
    }
}

final class PostgreSqlD2XConnection extends Connection
{
    /** @var list<int> */
    public array $requestedVisibleBeforeDiagnosticsRead = [];

    /** @var list<string> */
    public array $transactionIsolations = [];

    /** @var list<string> */
    public array $transactionReadOnlyStates = [];

    /** @var list<bool> */
    public array $diagnosticsReadAutoCommit = [];

    /** @var list<bool> */
    public array $diagnosticsReadTransactions = [];

    public int $diagnosticsReadAttempts = 0;

    public int $journalRowsConsumed = 0;

    public int $auditAppendAttempts = 0;

    public int $commitAttempts = 0;

    public int $rollbackAttempts = 0;

    public ?string $auditAppendFailure = null;

    public ?string $terminalCommitFailure = null;

    public ?string $journalFailure = null;

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
        if ($this->isDiagnosticsSourceQuery($query)) {
            $this->observeDiagnosticsRead();
        }

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        if ($this->isDiagnosticsSourceQuery($query)) {
            $this->observeDiagnosticsRead();
        }

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        if (!$this->isDiagnosticsJournalQuery($query)) {
            yield from parent::iterateAssociative($query, $params, $types);

            return;
        }

        $this->observeDiagnosticsRead();
        foreach (parent::iterateAssociative($query, $params, $types) as $row) {
            ++$this->journalRowsConsumed;
            if ($this->journalFailure !== null) {
                throw new \RuntimeException($this->journalFailure);
            }
            yield $row;
        }
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

    public function commit(): void
    {
        ++$this->commitAttempts;
        $readOnly = (string) $this->fetchOne('SHOW transaction_read_only');
        if ($this->terminalCommitFailure === 'before' && $this->auditAppendAttempts === 2) {
            throw new \RuntimeException('terminal commit failure');
        }
        parent::commit();
        if ($readOnly === 'on') {
            throw new \RuntimeException('unexpected read-only audit transaction');
        }
        if ($this->terminalCommitFailure === 'after' && $this->auditAppendAttempts === 2) {
            throw new \RuntimeException('terminal commit then throw');
        }
    }

    public function rollBack(): void
    {
        ++$this->rollbackAttempts;
        parent::rollBack();
    }

    private function observeDiagnosticsRead(): void
    {
        ++$this->diagnosticsReadAttempts;
        $this->diagnosticsReadAutoCommit[] = $this->isAutoCommit();
        $this->diagnosticsReadTransactions[] = $this->isTransactionActive();
        $this->requestedVisibleBeforeDiagnosticsRead[] = (int) $this->probe->fetchOne(
            'SELECT count(*) FROM "'
            . $this->schema
            . '"."audit_records"
             WHERE action_type = \'access.operation_diagnostics.requested\'',
        );
    }

    private function isDiagnosticsSourceQuery(string $query): bool
    {
        $schema = '"' . $this->schema . '"';

        return (
            str_contains($query, $schema . '."operations"') && str_contains($query, 'operation_id::text')
            || str_contains($query, $schema . '."dead_letters"') && str_contains($query, 'final_attempt_id')
            || str_contains($query, $schema . '."retention_purge_audits"') && str_contains($query, 'affected_count')
            || str_contains($query, $schema . '."outcomes"') && str_contains($query, 'encoded_payload')
        );
    }

    private function isDiagnosticsJournalQuery(string $query): bool
    {
        $schema = '"' . $this->schema . '"';

        return str_contains($query, $schema . '."journal"') && str_contains($query, 'encoded_record');
    }
}

final readonly class PostgreSqlD2XValue implements \BlackOps\Core\OperationValue
{
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $credential,
    ) {}
}

final readonly class PostgreSqlD2XOutcome implements Outcome
{
    public function __construct(
        public string $status,
        #[Sensitive]
        public string $secret,
    ) {}
}
