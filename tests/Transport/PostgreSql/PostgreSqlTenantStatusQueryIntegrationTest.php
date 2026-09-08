<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\Inline;
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
use BlackOps\Internal\Status\DefaultOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusDetailUnavailable;
use BlackOps\Internal\Status\OperationStatusJournalValidator;
use BlackOps\Internal\Status\OperationStatusSourceException;
use BlackOps\Internal\Status\OperationStatusSubject;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
use BlackOps\Internal\Status\TenantBoundOperationStatusSource;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
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
use BlackOps\Status\OperationStatusState;
use BlackOps\Status\OperationStatusUnavailable;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

/** @mago-expect lint:too-many-methods */
/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
final class PostgreSqlTenantStatusQueryIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_p23_d2s1_query';
    private const string DEFERRED_TYPE = 'status.tenant.deferred';
    private const string INLINE_TYPE = 'status.tenant.inline';
    private const string EPHEMERAL_TYPE = 'status.tenant.ephemeral';

    private Connection $connection;

    private PostgreSqlCanonicalJournalStore $journal;

    private PostgreSqlOutcomeStore $outcomes;

    private OperationRegistry $registry;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->migrate();
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->journal->migrate();
        $this->outcomes = new PostgreSqlOutcomeStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->registry = new OperationRegistry([
            $this->metadata(self::DEFERRED_TYPE, TenantStatusDeferredOperation::class, Deferred::class),
            $this->metadata(self::INLINE_TYPE, TenantStatusInlineOperation::class, Inline::class),
            $this->metadata(
                self::EPHEMERAL_TYPE,
                TenantStatusEphemeralOperation::class,
                Deferred::class,
                TenantStatusEphemeralOutcome::class,
            ),
        ]);
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        } finally {
            $this->connection->close();
        }
    }

    public function testBoundSourceSeparatesNamedGlobalCrossTenantAndAbsentWithoutProtectedDecode(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $namedId = $this->id(1);
        $globalId = $this->id(2);
        $globalDeferredId = $this->id(3);
        $namedDeferredId = $this->id(4);
        $this->append($this->inlineCompleted($namedId, $tenantA, new TenantStatusProjectionOutcome('named', 'old')));
        $this->append($this->inlineCompleted($globalId, null, new TenantStatusProjectionOutcome('global', 'old')));
        $this->createDeferred(
            $globalDeferredId,
            null,
            LifecycleState::Completed,
            new TenantStatusProjectionOutcome('global-deferred', 'global-deferred-private'),
        );
        $this->createDeferred(
            $namedDeferredId,
            $tenantA,
            LifecycleState::Completed,
            new TenantStatusProjectionOutcome('named-deferred', 'named-deferred-private'),
        );

        $named = $this->query($tenantA)->find($namedId);
        self::assertInstanceOf(OperationStatusFound::class, $named);
        self::assertSame(OperationStatusState::Completed, $named->status()->state());
        self::assertSame($tenantA->id(), $this->subject($namedId, $tenantA)->tenant?->id());
        self::assertInstanceOf(OperationStatusUnavailable::class, $this->query($tenantB)->find($namedId));
        self::assertInstanceOf(OperationStatusUnavailable::class, $this->query(null)->find($namedId));
        self::assertInstanceOf(OperationStatusFound::class, $this->query(null)->find($globalId));
        self::assertInstanceOf(OperationStatusUnavailable::class, $this->query($tenantA)->find($globalId));
        $globalDeferred = $this->query(null)->find($globalDeferredId);
        self::assertInstanceOf(OperationStatusFound::class, $globalDeferred);
        self::assertSame(OperationStatusState::Completed, $globalDeferred->status()->state());
        self::assertSame('global-deferred-private', $globalDeferred->status()->outcome()?->private);
        self::assertInstanceOf(OperationStatusUnavailable::class, $this->query($tenantA)->find($globalDeferredId));
        self::assertInstanceOf(OperationStatusUnavailable::class, $this->query($tenantA)->find($this->id(99)));

        $legacyNamed = $this->source()->findSubject($namedId);
        self::assertNotNull($legacyNamed);
        self::assertSame($tenantA->type(), $legacyNamed->tenant?->type());
        self::assertSame($tenantA->id(), $legacyNamed->tenant?->id());

        $recordingBase = $this->connection();
        $recordingConnection = new TenantStatusSqlRecordingConnection(
            $recordingBase->getParams(),
            $recordingBase->getDriver(),
        );
        $recordingBase->close();
        try {
            $legacyResult = new DefaultOperationStatusQuery(
                $this->sourceFor($recordingConnection),
                new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->find($namedDeferredId);
            self::assertInstanceOf(OperationStatusFound::class, $legacyResult);
            self::assertSame('named-deferred-private', $legacyResult->status()->outcome()?->private);
            $legacyStorageCalls = $this->storageCalls($recordingConnection->calls);
            self::assertNotEmpty($legacyStorageCalls);
            foreach ($legacyStorageCalls as $call) {
                self::assertStringNotContainsString('tenant_type IS NOT DISTINCT FROM :tenant_type', $call['sql']);
                self::assertStringNotContainsString('tenant_id IS NOT DISTINCT FROM :tenant_id', $call['sql']);
            }

            $recordingConnection->calls = [];
            $scopedKeys = new TenantStatusRecordingKeyProvider();
            $scopedGlobalResult = new DefaultOperationStatusQuery(
                new TenantBoundOperationStatusSource($this->sourceFor($recordingConnection, $scopedKeys), null),
                new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->find($globalDeferredId);
            self::assertInstanceOf(OperationStatusFound::class, $scopedGlobalResult);
            self::assertSame('global-deferred-private', $scopedGlobalResult->status()->outcome()?->private);
            $scopedGlobalStorageCalls = $this->storageCalls($recordingConnection->calls);
            self::assertNotEmpty($scopedGlobalStorageCalls);
            foreach ($scopedGlobalStorageCalls as $call) {
                self::assertStringContainsString('tenant_type IS NOT DISTINCT FROM :tenant_type', $call['sql']);
                self::assertStringContainsString('tenant_id IS NOT DISTINCT FROM :tenant_id', $call['sql']);
                self::assertArrayHasKey('tenant_type', $call['params']);
                self::assertArrayHasKey('tenant_id', $call['params']);
                self::assertNull($call['params']['tenant_type']);
                self::assertNull($call['params']['tenant_id']);
            }
            self::assertNotEmpty($scopedKeys->contexts);
            foreach ($scopedKeys->contexts as $context) {
                self::assertNull($context->tenant);
            }

            $recordingConnection->calls = [];
            $scopedNamedKeys = new TenantStatusRecordingKeyProvider();
            $scopedNamedResult = new DefaultOperationStatusQuery(
                new TenantBoundOperationStatusSource($this->sourceFor($recordingConnection, $scopedNamedKeys), null),
                new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
            )->find($namedDeferredId);
            self::assertInstanceOf(OperationStatusUnavailable::class, $scopedNamedResult);
            self::assertSame([], $scopedNamedKeys->contexts);
            foreach ($this->storageCalls($recordingConnection->calls) as $call) {
                self::assertStringContainsString('tenant_type IS NOT DISTINCT FROM :tenant_type', $call['sql']);
                self::assertStringContainsString('tenant_id IS NOT DISTINCT FROM :tenant_id', $call['sql']);
                self::assertNull($call['params']['tenant_type'] ?? null);
                self::assertNull($call['params']['tenant_id'] ?? null);
            }
        } finally {
            $recordingConnection->close();
        }

        $keys = new TenantStatusRecordingKeyProvider();
        $authorizer = new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow());
        $bound = new TenantBoundOperationStatusSource($this->source($keys), $tenantB);
        $result = new DefaultOperationStatusQuery($bound, $authorizer)->find($namedId);
        self::assertInstanceOf(OperationStatusUnavailable::class, $result);
        self::assertSame([], $keys->contexts);
        self::assertSame(0, $authorizer->calls);

        $deniedKeys = new TenantStatusRecordingKeyProvider();
        $denied = new DefaultOperationStatusQuery(
            new TenantBoundOperationStatusSource($this->source($deniedKeys), $tenantA),
            new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::deny()),
        )->find($namedId);
        self::assertInstanceOf(OperationStatusUnavailable::class, $denied);
        self::assertSame([], $deniedKeys->contexts);
    }

    /** @mago-expect lint:halstead */
    public function testScopedSourceProjectsEveryDeferredLifecycleStateAndSafeRetentionResults(): void
    {
        $tenant = $this->tenant('tenant-a');
        $states = [
            LifecycleState::Accepted,
            LifecycleState::Running,
            LifecycleState::Supervising,
            LifecycleState::RetryScheduled,
            LifecycleState::Completed,
            LifecycleState::Rejected,
            LifecycleState::Failed,
            LifecycleState::DeadLettered,
        ];
        $expected = [
            OperationStatusState::Accepted,
            OperationStatusState::Running,
            OperationStatusState::Running,
            OperationStatusState::RetryScheduled,
            OperationStatusState::Completed,
            OperationStatusState::Rejected,
            OperationStatusState::Failed,
            OperationStatusState::DeadLettered,
        ];

        foreach ($states as $offset => $state) {
            $id = $this->id(10 + $offset);
            $this->createDeferred($id, $tenant, $state);
            $result = $this->query($tenant)->find($id);
            self::assertInstanceOf(OperationStatusFound::class, $result, $state->value);
            self::assertSame($expected[$offset], $result->status()->state(), $state->value);
            if ($state === LifecycleState::RetryScheduled) {
                self::assertSame(1, $result->status()->attempt());
                self::assertSame(
                    '2026-07-19T00:01:00.143069+00:00',
                    $result->status()->retryAt()?->format('Y-m-d\TH:i:s.uP'),
                );
            }
        }

        $ephemeralId = $this->id(30);
        $this->enqueue($ephemeralId, $tenant, self::EPHEMERAL_TYPE);
        $ephemeralKeys = new TenantStatusRecordingKeyProvider();
        $ephemeral = new DefaultOperationStatusQuery(
            new TenantBoundOperationStatusSource($this->source($ephemeralKeys), $tenant),
            new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
        )->find($ephemeralId);
        self::assertInstanceOf(OperationStatusUnavailable::class, $ephemeral);
        self::assertSame([], $ephemeralKeys->contexts);

        $base = $this->connection();
        $ephemeralConnection = new TenantStatusBarrierConnection($base->getParams(), $base->getDriver(), null, null);
        $base->close();
        $ephemeralSourceKeys = new TenantStatusRecordingKeyProvider($ephemeralConnection);
        try {
            $ephemeralDetail = $this->sourceFor($ephemeralConnection, $ephemeralSourceKeys)->readDetailForTenant(
                $this->subjectForType($ephemeralId, self::EPHEMERAL_TYPE, $tenant),
                $tenant,
            );
            self::assertInstanceOf(OperationStatusDetailUnavailable::class, $ephemeralDetail);
            self::assertSame(0, $ephemeralConnection->beginCalls);
            self::assertSame(0, $ephemeralConnection->commitCalls);
            self::assertSame(0, $ephemeralConnection->rollbackCalls);
            self::assertFalse($ephemeralConnection->isTransactionActive());
            self::assertSame([], $ephemeralSourceKeys->contexts);
            self::assertSame([], $ephemeralSourceKeys->keyObservations);
        } finally {
            $ephemeralConnection->close();
        }

        $expiredOutcomeId = $this->id(31);
        $this->createDeferred($expiredOutcomeId, $tenant, LifecycleState::Completed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $expiredOutcomeId->toString()]);
        $this->insertPurge($expiredOutcomeId, RetentionPurgeTarget::Outcome, $tenant, 31);
        self::assertInstanceOf(OperationStatusExpired::class, $this->query($tenant)->find($expiredOutcomeId));

        $expiredJournalId = $this->id(32);
        $this->createDeferred($expiredJournalId, $tenant, LifecycleState::Rejected);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id', ['operation_id' => $expiredJournalId->toString()]);
        $this->insertPurge($expiredJournalId, RetentionPurgeTarget::Journal, $tenant, 32);
        self::assertInstanceOf(OperationStatusExpired::class, $this->query($tenant)->find($expiredJournalId));

        $failedPurgedId = $this->id(33);
        $this->createDeferred($failedPurgedId, $tenant, LifecycleState::Failed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id', ['operation_id' => $failedPurgedId->toString()]);
        $this->insertPurge($failedPurgedId, RetentionPurgeTarget::Journal, $tenant, 33);
        $failedPurged = $this->query($tenant)->find($failedPurgedId);
        self::assertInstanceOf(OperationStatusFound::class, $failedPurged);
        self::assertSame(OperationStatusState::Failed, $failedPurged->status()->state());

        $normalIncompleteId = $this->id(37);
        $this->createDeferred($normalIncompleteId, $tenant, LifecycleState::Completed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $normalIncompleteId->toString()]);
        $normalIncompleteKeys = new TenantStatusRecordingKeyProvider($this->connection);
        $normalIncomplete = $this->captureSourceCall(fn(): mixed => $this->source(
            $normalIncompleteKeys,
        )->readDetailForTenant($this->subject($normalIncompleteId, $tenant), $tenant));
        self::assertSame('Integrity', $normalIncomplete->failure->name);
        self::assertFalse($this->connection->isTransactionActive());
        $this->assertOwnKeyContexts($normalIncompleteKeys, $tenant);
        foreach ($normalIncompleteKeys->contexts as $context) {
            self::assertNotSame(\BlackOps\StorageProtection\StoragePurpose::OutcomePayload, $context->purpose);
        }

        $decodeFailureId = $this->id(38);
        $this->createDeferred($decodeFailureId, $tenant, LifecycleState::Completed);
        $this->connection->executeStatement('UPDATE '
        . self::SCHEMA
        . '.outcomes SET schema_version = 999 WHERE operation_id = :operation_id', [
            'operation_id' => $decodeFailureId->toString(),
        ]);
        $decodeKeys = new TenantStatusRecordingKeyProvider($this->connection);
        $decodeFailure = $this->captureSourceCall(fn(): mixed => $this->source($decodeKeys)->readDetailForTenant(
            $this->subject($decodeFailureId, $tenant),
            $tenant,
        ));
        self::assertSame('Decode', $decodeFailure->failure->name);
        self::assertSame('Operation status source failed.', $decodeFailure->getMessage());
        self::assertNull($decodeFailure->getPrevious());
        self::assertStringNotContainsString('schema_version', $decodeFailure->getMessage());
        self::assertFalse($this->connection->isTransactionActive());
        $this->assertOwnKeyContexts($decodeKeys, $tenant);
        $decodeQueryFailure = $this->captureFailure($this->query($tenant), $decodeFailureId);
        self::assertSame(OperationStatusQueryException::DECODE_FAILED, $decodeQueryFailure->queryCode());
        self::assertFalse($this->connection->isTransactionActive());

        $payloadPurgedId = $this->id(35);
        $this->createDeferred($payloadPurgedId, $tenant, LifecycleState::Failed);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
             SET encoded_payload = NULL, encoded_context = NULL, payload_purged_at = :purged_at
             WHERE operation_id = :operation_id',
            [
                'purged_at' => '2026-07-19T00:00:35Z',
                'operation_id' => $payloadPurgedId->toString(),
            ],
        );
        $this->insertPurge($payloadPurgedId, RetentionPurgeTarget::TransportPayload, $tenant, 35);
        $payloadPurged = $this->query($tenant)->find($payloadPurgedId);
        self::assertInstanceOf(OperationStatusFound::class, $payloadPurged);
        self::assertSame(OperationStatusState::Failed, $payloadPurged->status()->state());

        $deadLetterPurgedId = $this->id(36);
        $this->createDeferred($deadLetterPurgedId, $tenant, LifecycleState::DeadLettered);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.dead_letters WHERE operation_id = :operation_id', ['operation_id' => $deadLetterPurgedId->toString()]);
        $this->insertPurge($deadLetterPurgedId, RetentionPurgeTarget::DeadLetter, $tenant, 36);
        $deadLetterPurged = $this->query($tenant)->find($deadLetterPurgedId);
        self::assertInstanceOf(OperationStatusFound::class, $deadLetterPurged);
        self::assertSame(OperationStatusState::DeadLettered, $deadLetterPurged->status()->state());

        $malformedId = $this->id(34);
        $this->createDeferred($malformedId, $tenant, LifecycleState::Accepted);
        $this->connection->executeStatement('UPDATE '
        . self::SCHEMA
        . '.operations SET next_sequence = 999 WHERE operation_id = :operation_id', [
            'operation_id' => $malformedId->toString(),
        ]);
        $failure = $this->captureFailure($this->query($tenant), $malformedId);
        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $failure->queryCode());
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testForeignRowsAndCiphertextCanariesAreIndistinguishableFromAbsence(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $foreignJournalId = $this->id(40);
        $this->createDeferred($foreignJournalId, $tenantA, LifecycleState::Failed);
        $this->insertForeignJournal($foreignJournalId, $tenantB, 99);
        $journalKeys = new TenantStatusRecordingKeyProvider();
        $result = $this->query($tenantA, $journalKeys)->find($foreignJournalId);
        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertSame(OperationStatusState::Failed, $result->status()->state());
        $this->assertOwnKeyContexts($journalKeys, $tenantA);

        $foreignOutcomeId = $this->id(41);
        $this->createDeferred($foreignOutcomeId, $tenantA, LifecycleState::Failed);
        $this->insertRawOutcome($foreignOutcomeId, $tenantB, 'foreign-outcome-ciphertext', 41);
        $outcomeKeys = new TenantStatusRecordingKeyProvider();
        $outcomeResult = $this->query($tenantA, $outcomeKeys)->find($foreignOutcomeId);
        self::assertInstanceOf(OperationStatusFound::class, $outcomeResult);
        self::assertSame(OperationStatusState::Failed, $outcomeResult->status()->state());
        $this->assertOwnKeyContexts($outcomeKeys, $tenantA);

        $foreignTransportId = $this->id(42);
        $this->enqueue($foreignTransportId, $tenantB);
        $transportKeys = new TenantStatusRecordingKeyProvider();
        self::assertInstanceOf(
            OperationStatusUnavailable::class,
            $this->query($tenantA, $transportKeys)->find($foreignTransportId),
        );
        self::assertSame([], $transportKeys->contexts);

        $foreignDeadLetterId = $this->id(43);
        $this->createDeferred($foreignDeadLetterId, $tenantA, LifecycleState::Failed);
        $this->insertRawDeadLetter($foreignDeadLetterId, $tenantB, 43);
        $deadLetterKeys = new TenantStatusRecordingKeyProvider();
        $deadLetterResult = $this->query($tenantA, $deadLetterKeys)->find($foreignDeadLetterId);
        self::assertInstanceOf(OperationStatusFound::class, $deadLetterResult);
        self::assertSame(OperationStatusState::Failed, $deadLetterResult->status()->state());
        $this->assertOwnKeyContexts($deadLetterKeys, $tenantA);

        $foreignPurgeId = $this->id(44);
        $this->createDeferred($foreignPurgeId, $tenantA, LifecycleState::Failed);
        $this->insertPurge($foreignPurgeId, RetentionPurgeTarget::Outcome, $tenantB, 44);
        $purgeKeys = new TenantStatusRecordingKeyProvider();
        $purgeResult = $this->query($tenantA, $purgeKeys)->find($foreignPurgeId);
        self::assertInstanceOf(OperationStatusFound::class, $purgeResult);
        self::assertSame(OperationStatusState::Failed, $purgeResult->status()->state());
        $this->assertOwnKeyContexts($purgeKeys, $tenantA);

        $foreignDetailId = $this->id(45);
        $this->enqueue($foreignDetailId, $tenantB);
        $this->insertRawOutcome($foreignDetailId, $tenantB, 'foreign-detail-ciphertext', 45);
        $detailKeys = new TenantStatusRecordingKeyProvider();
        self::assertInstanceOf(
            OperationStatusUnavailable::class,
            $this->query($tenantA, $detailKeys)->find($foreignDetailId),
        );
        self::assertSame([], $detailKeys->contexts);

        $completedId = $this->id(46);
        $this->createDeferred($completedId, $tenantA, LifecycleState::Completed);
        $this->moveOutcomeToTenant($completedId, $tenantB, 'foreign-completed-ciphertext');
        $completedKeys = new TenantStatusRecordingKeyProvider();
        $failure = $this->captureFailure($this->query($tenantA, $completedKeys), $completedId);
        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $failure->queryCode());
        $this->assertOwnKeyContexts($completedKeys, $tenantA);
        foreach ($completedKeys->contexts as $context) {
            self::assertNotSame(\BlackOps\StorageProtection\StoragePurpose::OutcomePayload, $context->purpose);
        }
    }

    public function testTenantMoveAndIdentityDriftAfterInitialLookupFailBeforeProtectedDecode(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $moveId = $this->id(50);
        $this->createDeferred($moveId, $tenantA, LifecycleState::Failed);
        $secondary = $this->connection();
        $primary = $this->barrierConnection(static function () use ($secondary, $tenantB, $moveId): void {
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.operations SET tenant_type = :type, tenant_id = :id WHERE operation_id = :operation_id', [
                'type' => $tenantB->type(),
                'id' => $tenantB->id(),
                'operation_id' => $moveId->toString(),
            ]);
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.journal SET tenant_type = :type, tenant_id = :id WHERE operation_id = :operation_id', [
                'type' => $tenantB->type(),
                'id' => $tenantB->id(),
                'operation_id' => $moveId->toString(),
            ]);
        });
        $keys = new TenantStatusRecordingKeyProvider();
        try {
            $result = $this->queryUsing($primary, $tenantA, $keys)->find($moveId);
            self::assertInstanceOf(OperationStatusUnavailable::class, $result);
            self::assertSame([], $keys->contexts);
            self::assertFalse($primary->isTransactionActive());
        } finally {
            $primary->close();
            $secondary->close();
        }

        $driftId = $this->id(51);
        $this->createDeferred($driftId, $tenantA, LifecycleState::Failed);
        $secondary = $this->connection();
        $primary = $this->barrierConnection(static function () use ($secondary, $driftId): void {
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.operations SET origin_actor_type = :type, origin_actor_id = :id WHERE operation_id = :operation_id', [
                'type' => 'customer',
                'id' => 'drifted-origin',
                'operation_id' => $driftId->toString(),
            ]);
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.journal SET origin_actor_type = :type, origin_actor_id = :id WHERE operation_id = :operation_id', [
                'type' => 'customer',
                'id' => 'drifted-origin',
                'operation_id' => $driftId->toString(),
            ]);
        });
        $keys = new TenantStatusRecordingKeyProvider();
        try {
            $failure = $this->captureFailure($this->queryUsing($primary, $tenantA, $keys), $driftId);
            self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $failure->queryCode());
            self::assertSame([], $keys->contexts);
            self::assertFalse($primary->isTransactionActive());
        } finally {
            $primary->close();
            $secondary->close();
        }

        $typeDriftId = $this->id(53);
        $this->createDeferred($typeDriftId, $tenantA, LifecycleState::Failed);
        $secondary = $this->connection();
        $primary = $this->barrierConnection(static function () use ($secondary, $typeDriftId): void {
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.operations SET operation_type = :operation_type WHERE operation_id = :operation_id', [
                'operation_type' => 'status.tenant.drifted',
                'operation_id' => $typeDriftId->toString(),
            ]);
            $secondary->executeStatement('UPDATE '
            . self::SCHEMA
            . '.journal SET operation_type = :operation_type WHERE operation_id = :operation_id', [
                'operation_type' => 'status.tenant.drifted',
                'operation_id' => $typeDriftId->toString(),
            ]);
        });
        $typeDriftKeys = new TenantStatusRecordingKeyProvider($primary);
        try {
            $failure = $this->captureFailure($this->queryUsing($primary, $tenantA, $typeDriftKeys), $typeDriftId);
            self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $failure->queryCode());
            self::assertSame([], $typeDriftKeys->contexts);
            self::assertSame([], $typeDriftKeys->keyObservations);
            self::assertFalse($primary->isTransactionActive());
            self::assertNotEmpty($primary->levelObservations);
            self::assertSame(0, $primary->levelObservations[0]);
            self::assertSame(0, $primary->levelObservations[count($primary->levelObservations) - 1]);
        } finally {
            $primary->close();
            $secondary->close();
        }

        $removedId = $this->id(52);
        $this->createDeferred($removedId, $tenantA, LifecycleState::Failed);
        $secondary = $this->connection();
        $primary = $this->barrierConnection(static function () use ($secondary, $removedId): void {
            $secondary->executeStatement('DELETE FROM '
            . self::SCHEMA
            . '.journal WHERE operation_id = :operation_id', ['operation_id' => $removedId->toString()]);
            $secondary->executeStatement('DELETE FROM '
            . self::SCHEMA
            . '.operations WHERE operation_id = :operation_id', ['operation_id' => $removedId->toString()]);
        });
        $removedKeys = new TenantStatusRecordingKeyProvider($primary);
        try {
            self::assertInstanceOf(
                OperationStatusUnavailable::class,
                $this->queryUsing($primary, $tenantA, $removedKeys)->find($removedId),
            );
            self::assertSame([], $removedKeys->contexts);
            self::assertFalse($primary->isTransactionActive());
        } finally {
            $primary->close();
            $secondary->close();
        }
    }

    public function testRepeatableReadSnapshotSurvivesSecondConnectionMutationAndLaterSnapshotSeesIt(): void
    {
        $tenant = $this->tenant('tenant-a');
        $id = $this->id(60);
        $this->createDeferred(
            $id,
            $tenant,
            LifecycleState::Completed,
            new TenantStatusProjectionOutcome('old', 'old-private'),
        );
        $secondary = $this->connection();
        $secondaryBase = $secondary;
        $secondary = new TenantStatusBarrierConnection(
            $secondaryBase->getParams(),
            $secondaryBase->getDriver(),
            null,
            null,
        );
        $secondaryBase->close();
        $primary = $this->barrierConnection(null, function () use ($secondary, $id, $tenant): void {
            $secondary->beginTransaction();
            $this->replaceOutcome($secondary, $id, $tenant, new TenantStatusProjectionOutcome('new', 'new-private'));
            $this->replaceSubjectIdentity($secondary, $id, $tenant, new ActorRef('origin-b', 'customer'));
            $secondary->commit();
        });
        $keys = new TenantStatusRecordingKeyProvider($primary);
        try {
            $query = $this->queryUsing($primary, $tenant, $keys);
            $result = $query->find($id);
            self::assertInstanceOf(OperationStatusFound::class, $result);
            self::assertInstanceOf(TenantStatusProjectionOutcome::class, $result->status()->outcome());
            self::assertSame('old-private', $result->status()->outcome()->private);
            self::assertNotEmpty($primary->activeObservations);
            self::assertNotContains(false, $primary->activeObservations);
            self::assertSame('repeatable read', $primary->snapshotIsolation);
            self::assertSame('on', $primary->snapshotReadOnly);
            self::assertSame(1, $primary->beginCalls);
            self::assertSame(1, $primary->commitCalls);
            self::assertSame(0, $primary->rollbackCalls);
            self::assertFalse($primary->isTransactionActive());
            self::assertNotEmpty($primary->levelObservations);
            self::assertSame(0, $primary->levelObservations[0]);
            self::assertSame(0, $primary->levelObservations[count($primary->levelObservations) - 1]);
            self::assertNotEmpty($primary->subjectIdentityObservations);
            self::assertSame(['origin-a'], array_values(array_unique($primary->subjectIdentityObservations)));
            self::assertNotEmpty($keys->keyObservations);
            foreach ($keys->keyObservations as $observation) {
                self::assertTrue($observation['active']);
                self::assertSame(1, $observation['nesting']);
                self::assertSame('repeatable read', $observation['isolation']);
                self::assertSame('on', $observation['read_only']);
            }

            $laterConnection = $this->connection();
            $later = $this->queryUsing($laterConnection, $tenant)->find($id);
            self::assertInstanceOf(OperationStatusFound::class, $later);
            self::assertSame('new-private', $later->status()->outcome()->private);
            $laterSubject = $this->sourceFor($laterConnection)->findSubjectForTenant($id, $tenant);
            self::assertNotNull($laterSubject);
            self::assertSame('origin-b', $laterSubject->originActor?->id());
            self::assertSame('customer', $laterSubject->originActor?->type());
            self::assertSame(1, $secondary->beginCalls);
            self::assertSame(1, $secondary->commitCalls);
            self::assertSame(0, $secondary->rollbackCalls);
            self::assertFalse($secondary->isTransactionActive());
            $laterConnection->close();
        } finally {
            $primary->close();
            $secondary->close();
        }
    }

    /** @mago-expect lint:halstead */
    public function testScopedEntryStateAndTransactionFailureContractIsFailClosed(): void
    {
        $tenant = $this->tenant('tenant-a');
        $id = $this->id(70);
        $this->createDeferred($id, $tenant, LifecycleState::Failed);
        $subject = $this->subject($id, $tenant);

        $base = $this->connection();
        $noIo = new TenantStatusNoIoConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $mismatch = $this->sourceFor($noIo)->readDetailForTenant($subject, $this->tenant('tenant-b'));
            self::assertInstanceOf(OperationStatusDetailUnavailable::class, $mismatch);
            self::assertSame(0, $noIo->beginCalls);
            self::assertSame(0, $noIo->commitCalls);
            self::assertSame(0, $noIo->rollbackCalls);
        } finally {
            $noIo->close();
        }

        $base = $this->connection();
        $begin = new TenantStatusBeginFailureConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($begin)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $begin->beginCalls);
            self::assertSame(0, $begin->rollbackCalls);
            self::assertFalse($begin->isTransactionActive());
        } finally {
            $begin->close();
        }

        $base = $this->connection();
        $beginAfterStart = new TenantStatusBeginAfterStartFailureConnection($base->getParams(), $base->getDriver());
        $base->close();
        $beginAfterStartKeys = new TenantStatusRecordingKeyProvider();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($beginAfterStart, $beginAfterStartKeys)->readDetailForTenant(
                    $subject,
                    $tenant,
                ),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $beginAfterStart->beginCalls);
            self::assertSame(0, $beginAfterStart->commitCalls);
            self::assertSame(0, $beginAfterStart->rollbackCalls);
            self::assertTrue($beginAfterStart->isTransactionActive());
            self::assertSame(1, $beginAfterStart->getTransactionNestingLevel());
            self::assertSame([], $beginAfterStartKeys->contexts);
        } finally {
            $beginAfterStart->close();
        }

        $base = $this->connection();
        $ownershipInspection = new TenantStatusOwnershipInspectionConnection($base->getParams(), $base->getDriver());
        $base->close();
        $ownershipKeys = new TenantStatusRecordingKeyProvider();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($ownershipInspection, $ownershipKeys)->readDetailForTenant(
                    $subject,
                    $tenant,
                ),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $ownershipInspection->beginCalls);
            self::assertSame(0, $ownershipInspection->commitCalls);
            self::assertSame(0, $ownershipInspection->rollbackCalls);
            self::assertTrue($ownershipInspection->isTransactionActive());
            self::assertSame(1, $ownershipInspection->getTransactionNestingLevel());
            self::assertSame(3, $ownershipInspection->nestingInspections);
            self::assertSame([], $ownershipKeys->contexts);
        } finally {
            $ownershipInspection->close();
        }

        $base = $this->connection();
        $commit = new TenantStatusCommitFailureConnection($base->getParams(), $base->getDriver(), false);
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($commit)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $commit->beginCalls);
            self::assertSame(1, $commit->commitCalls);
            self::assertSame(1, $commit->rollbackCalls);
            self::assertFalse($commit->isTransactionActive());
        } finally {
            $commit->close();
        }

        $base = $this->connection();
        $commitAfter = new TenantStatusCommitFailureConnection($base->getParams(), $base->getDriver(), true);
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($commitAfter)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(0, $commitAfter->rollbackCalls);
            self::assertSame(1, $commitAfter->commitCalls);
            self::assertFalse($commitAfter->isTransactionActive());
        } finally {
            $commitAfter->close();
        }

        $incompleteId = $this->id(71);
        $this->createDeferred($incompleteId, $tenant, LifecycleState::Completed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $incompleteId->toString()]);
        $base = $this->connection();
        $rollback = new TenantStatusRollbackFailureConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(fn(): mixed => $this->sourceFor($rollback)->readDetailForTenant(
                $this->subject($incompleteId, $tenant),
                $tenant,
            ));
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $rollback->rollbackCalls);
            self::assertTrue($rollback->isTransactionActive());
        } finally {
            $rollback->close();
        }

        $base = $this->connection();
        $postCommit = new TenantStatusPostStateConnection($base->getParams(), $base->getDriver(), 'commit');
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($postCommit)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(0, $postCommit->rollbackCalls);
            self::assertSame(1, $postCommit->commitCalls);
            self::assertFalse($postCommit->isTransactionActive());
        } finally {
            $postCommit->close();
        }

        $base = $this->connection();
        $postRollback = new TenantStatusPostStateConnection($base->getParams(), $base->getDriver(), 'rollback');
        $base->close();
        try {
            $failure = $this->captureSourceCall(fn(): mixed => $this->sourceFor($postRollback)->readDetailForTenant(
                $this->subject($incompleteId, $tenant),
                $tenant,
            ));
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $postRollback->rollbackCalls);
            self::assertFalse($postRollback->isTransactionActive());
        } finally {
            $postRollback->close();
        }

        $base = $this->connection();
        $ambiguous = new TenantStatusAmbiguousConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($ambiguous)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $ambiguous->beginCalls);
            self::assertSame(0, $ambiguous->rollbackCalls);
            self::assertTrue($ambiguous->isTransactionActive());
        } finally {
            $ambiguous->close();
        }

        $base = $this->connection();
        $inspection = new TenantStatusInspectionFailureConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($inspection)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(0, $inspection->beginCalls);
        } finally {
            $inspection->close();
        }

        $base = $this->connection();
        $drift = new TenantStatusSubjectDriftConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($drift)->findSubjectForTenant($id, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(0, $drift->beginCalls);
        } finally {
            $drift->close();
        }

        $base = $this->connection();
        $recheck = new TenantStatusRecheckStorageConnection($base->getParams(), $base->getDriver());
        $base->close();
        $recheckKeys = new TenantStatusRecordingKeyProvider();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($recheck, $recheckKeys)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $recheck->beginCalls);
            self::assertSame(1, $recheck->rollbackCalls);
            self::assertSame(0, $recheck->commitCalls);
            self::assertFalse($recheck->isTransactionActive());
            self::assertSame(0, $recheck->getTransactionNestingLevel());
            self::assertSame([], $recheckKeys->contexts);
        } finally {
            $recheck->close();
        }

        $base = $this->connection();
        $nonAutoCommit = new TenantStatusNonAutoCommitConnection($base->getParams(), $base->getDriver());
        $base->close();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($nonAutoCommit)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(0, $nonAutoCommit->beginCalls);
            self::assertSame(0, $nonAutoCommit->commitCalls);
            self::assertSame(0, $nonAutoCommit->rollbackCalls);
            self::assertFalse($nonAutoCommit->isTransactionActive());
        } finally {
            $nonAutoCommit->close();
        }

        $base = $this->connection();
        $ambient = new TenantStatusBarrierConnection($base->getParams(), $base->getDriver(), null, null);
        $base->close();
        $ambient->beginTransaction();
        try {
            $failure = $this->captureSourceCall(
                fn(): mixed => $this->sourceFor($ambient)->readDetailForTenant($subject, $tenant),
            );
            self::assertSame('Storage', $failure->failure->name);
            self::assertSame(1, $ambient->beginCalls);
            self::assertSame(0, $ambient->commitCalls);
            self::assertSame(0, $ambient->rollbackCalls);
            self::assertTrue($ambient->isTransactionActive());
            self::assertSame(1, $ambient->getTransactionNestingLevel());
        } finally {
            $ambient->rollBack();
            $ambient->close();
        }
    }

    private function query(
        ?TenantRef $tenant,
        ?TenantStatusRecordingKeyProvider $keys = null,
    ): DefaultOperationStatusQuery {
        return $this->queryUsing($this->connection, $tenant, $keys);
    }

    private function queryUsing(
        Connection $connection,
        ?TenantRef $tenant,
        ?TenantStatusRecordingKeyProvider $keys = null,
    ): DefaultOperationStatusQuery {
        $source = $this->sourceFor($connection, $keys);

        return new DefaultOperationStatusQuery(
            new TenantBoundOperationStatusSource($source, $tenant),
            new TenantStatusAuthorizer(OperationStatusAuthorizationDecision::allow()),
        );
    }

    private function source(?TenantStatusRecordingKeyProvider $keys = null): PostgreSqlOperationStatusSource
    {
        return $this->sourceFor($this->connection, $keys);
    }

    private function sourceFor(
        Connection $connection,
        ?TenantStatusRecordingKeyProvider $keys = null,
    ): PostgreSqlOperationStatusSource {
        return new PostgreSqlOperationStatusSource(
            $connection,
            $this->registry,
            $keys === null
                ? PostgreSqlTestStorageProtection::codec()
                : new \BlackOps\Internal\StorageProtection\BopdEnvelopeCodec($keys),
            self::SCHEMA,
            new OperationStatusJournalValidator(),
        );
    }

    /**
     * @param list<array{method:string,sql:string,params:array<string,mixed>}> $calls
     * @return list<array{method:string,sql:string,params:array<string,mixed>}>
     */
    private function storageCalls(array $calls): array
    {
        return array_values(array_filter($calls, static fn(array $call): bool => str_contains(
            $call['sql'],
            self::SCHEMA,
        )));
    }

    private function createDeferred(
        OperationId $id,
        ?TenantRef $tenant,
        LifecycleState $state,
        ?TenantStatusProjectionOutcome $outcome = null,
    ): void {
        $this->enqueue($id, $tenant);
        $records = $this->deferredRecords($id, $tenant, $state);
        $this->append($records);
        $active = $state === LifecycleState::Running;
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
             SET state = :state, next_sequence = :next_sequence, attempt_number = :attempt_number,
                 current_attempt_id = :attempt_id, current_attempt_started_at = :attempt_started,
                 available_at = :available_at
             WHERE operation_id = :operation_id',
            [
                'state' => $state->value,
                'next_sequence' => count($records) + 1,
                'attempt_number' => $state === LifecycleState::Accepted ? 0 : 1,
                'attempt_id' => $active ? $this->attemptId($id)->toString() : null,
                'attempt_started' => $active ? '2026-07-19T00:00:03Z' : null,
                'available_at' => $state === LifecycleState::RetryScheduled
                    ? '2026-07-19T00:01:00.143069Z'
                    : '2026-07-19T00:00:00Z',
                'operation_id' => $id->toString(),
            ],
        );
        if ($state === LifecycleState::Completed) {
            $this->outcomes->save(
                new OutcomeRecord(
                    $id,
                    $outcome ?? new TenantStatusProjectionOutcome('ready', 'old-private'),
                    new DateTimeImmutable('2026-07-19T00:00:06Z'),
                ),
            );
        }
        if ($state === LifecycleState::DeadLettered) {
            $this->insertRawDeadLetter($id, $tenant, (int) substr($id->toString(), -2));
        }
    }

    /** @return list<JournalRecord> */
    private function deferredRecords(
        OperationId $id,
        ?TenantRef $tenant,
        LifecycleState $state,
        ?ActorRef $originActor = null,
    ): array {
        $attempt = $this->attempt($id);
        $records = [
            $this->record(
                $id,
                self::DEFERRED_TYPE,
                $tenant,
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new TenantStatusValue('private')),
                originActor: $originActor,
            ),
            $this->record(
                $id,
                self::DEFERRED_TYPE,
                $tenant,
                2,
                JournalEvent::OperationAccepted,
                originActor: $originActor,
            ),
        ];
        if ($state === LifecycleState::Accepted) {
            return $records;
        }
        $records[] = $this->record(
            $id,
            self::DEFERRED_TYPE,
            $tenant,
            3,
            JournalEvent::AttemptStarted,
            attempt: $attempt,
            originActor: $originActor,
        );
        if ($state === LifecycleState::Running) {
            return $records;
        }
        if (in_array($state, [LifecycleState::Completed, LifecycleState::Rejected], strict: true)) {
            $records[] = $state === LifecycleState::Completed
                ? $this->record(
                    $id,
                    self::DEFERRED_TYPE,
                    $tenant,
                    4,
                    JournalEvent::AttemptSucceeded,
                    attempt: $attempt,
                    originActor: $originActor,
                )
                : $this->record(
                    $id,
                    self::DEFERRED_TYPE,
                    $tenant,
                    4,
                    JournalEvent::OperationRejected,
                    new OperationRejectedData(TenantStatusFixtures::rejection()),
                    $attempt,
                    originActor: $originActor,
                );
            if ($state === LifecycleState::Completed) {
                $records[] = $this->record(
                    $id,
                    self::DEFERRED_TYPE,
                    $tenant,
                    5,
                    JournalEvent::OperationCompleted,
                    new OperationCompletedData(new TenantStatusProjectionOutcome('ready', 'journal-private')),
                    $attempt,
                    originActor: $originActor,
                );
            }

            return $records;
        }
        $records[] = $this->record(
            $id,
            self::DEFERRED_TYPE,
            $tenant,
            4,
            JournalEvent::AttemptFailed,
            new AttemptFailedData('private-failure', 'private-attempt', true),
            $attempt,
            originActor: $originActor,
        );
        if ($state === LifecycleState::Supervising) {
            return $records;
        }
        $terminal = match ($state) {
            LifecycleState::RetryScheduled => new AttemptRetryScheduledData(
                $attempt->id,
                2,
                new DateTimeImmutable('2026-07-19T00:01:00.143069Z'),
                60_000,
            ),
            LifecycleState::Failed => new OperationFailedData('private-failure', 'private-failure', false),
            LifecycleState::DeadLettered => new OperationDeadLetteredData(
                $attempt->id,
                1,
                'private-failure',
                'private-dead',
                new DateTimeImmutable('2026-07-19T00:00:05.654321Z'),
            ),
            default => throw new \LogicException('Unexpected lifecycle fixture.'),
        };
        $event = match ($state) {
            LifecycleState::RetryScheduled => JournalEvent::AttemptRetryScheduled,
            LifecycleState::Failed => JournalEvent::OperationFailed,
            LifecycleState::DeadLettered => JournalEvent::OperationDeadLettered,
            default => throw new \LogicException('Unexpected terminal fixture.'),
        };
        $records[] = $this->record(
            $id,
            self::DEFERRED_TYPE,
            $tenant,
            5,
            $event,
            $terminal,
            $attempt,
            originActor: $originActor,
        );

        return $records;
    }

    /** @return list<JournalRecord> */
    private function inlineCompleted(OperationId $id, ?TenantRef $tenant, TenantStatusProjectionOutcome $outcome): array
    {
        $attempt = $this->attempt($id);

        return [
            $this->record(
                $id,
                self::INLINE_TYPE,
                $tenant,
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new TenantStatusValue('private')),
            ),
            $this->record($id, self::INLINE_TYPE, $tenant, 2, JournalEvent::AttemptStarted, attempt: $attempt),
            $this->record($id, self::INLINE_TYPE, $tenant, 3, JournalEvent::AttemptSucceeded, attempt: $attempt),
            $this->record(
                $id,
                self::INLINE_TYPE,
                $tenant,
                4,
                JournalEvent::OperationCompleted,
                new OperationCompletedData($outcome),
                $attempt,
            ),
        ];
    }

    /** @param list<JournalRecord> $records */
    private function append(array $records): void
    {
        foreach ($records as $record) {
            $this->journal->append($record);
        }
    }

    private function enqueue(OperationId $id, ?TenantRef $tenant, string $type = self::DEFERRED_TYPE): void
    {
        new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->enqueue(
            new DeferredOperationMessage(
                $id,
                $type,
                1,
                '{"private":"payload"}',
                '{"private":"context"}',
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
                tenant: $tenant,
                originActor: new ActorRef('origin-a', 'customer'),
            ),
        );
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function record(
        OperationId $id,
        string $type,
        ?TenantRef $tenant,
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        ?JournalAttempt $attempt = null,
        ?ActorRef $originActor = null,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf(
                '019f70ab-5000-7000-8000-%012d',
                ((int) substr($id->toString(), -6) * 10) + $sequence,
            )),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-07-19T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $id,
                $type,
                1,
                $type === self::INLINE_TYPE ? 'inline' : 'deferred',
                CorrelationId::fromString('019f70ab-6000-7000-8000-' . substr($id->toString(), -12)),
                actorContext: new ActorContext(
                    $originActor ?? new ActorRef('origin-a', 'customer'),
                    null,
                    new ActorRef('execution-a', 'worker'),
                ),
                tenant: $tenant,
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    private function attempt(OperationId $id): JournalAttempt
    {
        return new JournalAttempt($this->attemptId($id), 1, new DateTimeImmutable('2026-07-19T00:00:03Z'));
    }

    private function attemptId(OperationId $id): AttemptId
    {
        return AttemptId::fromString('019f70ab-8000-7000-8000-' . substr($id->toString(), -12));
    }

    private function insertPurge(OperationId $id, RetentionPurgeTarget $target, TenantRef $tenant, int $number): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.retention_purge_audits (
                audit_id, operation_id, target, affected_count, policy, purged_at, purged_by, tenant_type, tenant_id
             ) VALUES (:audit_id, :operation_id, :target, 1, :policy, :purged_at, :purged_by, :tenant_type, :tenant_id)',
            [
                'audit_id' => sprintf('019f70ab-9000-7000-8000-%012d', $number),
                'operation_id' => $id->toString(),
                'target' => $target->value,
                'policy' => 'tenant-query',
                'purged_at' => sprintf('2026-07-19T00:00:%02dZ', $number % 60),
                'purged_by' => 'tenant-query',
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
            ],
        );
    }

    private function insertRawOutcome(OperationId $id, TenantRef $tenant, string $plaintext, int $number): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outcomes (
                operation_id, outcome_type, schema_version, tenant_type, tenant_id, encoded_payload, completed_at
             ) VALUES (:operation_id, :outcome_type, 2, :tenant_type, :tenant_id, :encoded_payload, :completed_at)',
            [
                'operation_id' => $id->toString(),
                'outcome_type' => TenantStatusProjectionOutcome::class,
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
                'encoded_payload' => PostgreSqlTestStorageProtection::outcomeEnvelope(
                    $plaintext,
                    $id->toString(),
                    self::DEFERRED_TYPE,
                    tenant: $tenant,
                ),
                'completed_at' => sprintf('2026-07-19T00:00:%02dZ', $number % 60),
            ],
        );
    }

    private function moveOutcomeToTenant(OperationId $id, TenantRef $tenant, string $plaintext): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.outcomes
             SET tenant_type = :tenant_type, tenant_id = :tenant_id, encoded_payload = :encoded_payload
             WHERE operation_id = :operation_id',
            [
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
                'encoded_payload' => PostgreSqlTestStorageProtection::outcomeEnvelope(
                    $plaintext,
                    $id->toString(),
                    self::DEFERRED_TYPE,
                    tenant: $tenant,
                ),
                'operation_id' => $id->toString(),
            ],
            ['encoded_payload' => ParameterType::BINARY],
        );
    }

    private function insertRawDeadLetter(OperationId $id, TenantRef $tenant, int $number): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.dead_letters (
                operation_id, final_attempt_number, encoded_reason, moved_at, tenant_type, tenant_id
             ) VALUES (:operation_id, 1, decode(\'424f5044\', \'hex\'), :moved_at, :tenant_type, :tenant_id)',
            [
                'operation_id' => $id->toString(),
                'moved_at' => sprintf('2026-07-19T00:00:%02dZ', $number % 60),
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
            ],
        );
    }

    private function insertForeignJournal(OperationId $id, TenantRef $tenant, int $sequence): void
    {
        $recordId = sprintf('019f70ab-a000-7000-8000-%012d', $sequence);
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.journal (
                record_id, operation_id, operation_type, sequence, event, schema_version,
                operation_schema_version, occurred_at, tenant_type, tenant_id,
                origin_actor_type, origin_actor_id, encoded_record
             ) VALUES (:record_id, :operation_id, :operation_type, :sequence, :event, 1, 1,
                       :occurred_at, :tenant_type, :tenant_id, :origin_type, :origin_id,
                       :encoded_record)', [
            'record_id' => $recordId,
            'operation_id' => $id->toString(),
            'operation_type' => self::DEFERRED_TYPE,
            'sequence' => $sequence,
            'event' => JournalEvent::OperationFailed->value,
            'occurred_at' => '2026-07-19T00:00:09Z',
            'tenant_type' => $tenant->type(),
            'tenant_id' => $tenant->id(),
            'origin_type' => 'customer',
            'origin_id' => 'foreign-origin',
            'encoded_record' => PostgreSqlTestStorageProtection::journalEnvelope(
                'foreign-journal-ciphertext',
                $recordId,
                $id->toString(),
                self::DEFERRED_TYPE,
                1,
                $tenant,
            ),
        ]);
    }

    private function replaceOutcome(
        Connection $connection,
        OperationId $id,
        TenantRef $tenant,
        TenantStatusProjectionOutcome $outcome,
    ): void {
        $encoded = new PostgreSqlOutcomeCodec()->encode($outcome);
        $protected = PostgreSqlTestStorageProtection::codec()->encrypt(
            $encoded->payload,
            new \BlackOps\Internal\StorageProtection\StorageProtectionContext(
                \BlackOps\StorageProtection\StoragePurpose::OutcomePayload,
                $id->toString(),
                $id->toString(),
                self::DEFERRED_TYPE,
                1,
                $tenant,
            ),
        );
        $connection->executeStatement(
            'UPDATE '
            . self::SCHEMA
            . '.outcomes SET encoded_payload = :encoded_payload, outcome_type = :outcome_type, schema_version = 2 WHERE operation_id = :operation_id',
            [
                'encoded_payload' => $protected,
                'outcome_type' => TenantStatusProjectionOutcome::class,
                'operation_id' => $id->toString(),
            ],
            ['encoded_payload' => ParameterType::BINARY],
        );
    }

    private function replaceSubjectIdentity(
        Connection $connection,
        OperationId $id,
        TenantRef $tenant,
        ActorRef $originActor,
    ): void {
        $records = $this->deferredRecords($id, $tenant, LifecycleState::Completed, $originActor);
        foreach ($records as $record) {
            $connection->executeStatement(
                'UPDATE '
                . self::SCHEMA
                . '.journal
                 SET origin_actor_type = :origin_type,
                     origin_actor_id = :origin_id,
                     encoded_record = :encoded_record
                 WHERE operation_id = :operation_id AND sequence = :sequence',
                [
                    'origin_type' => $originActor->type(),
                    'origin_id' => $originActor->id(),
                    'encoded_record' => PostgreSqlTestStorageProtection::journalRecordEnvelope($record),
                    'operation_id' => $id->toString(),
                    'sequence' => $record->sequence,
                ],
                ['encoded_record' => ParameterType::BINARY],
            );
        }
        $connection->executeStatement('UPDATE ' . self::SCHEMA . '.operations
             SET origin_actor_type = :origin_type, origin_actor_id = :origin_id
             WHERE operation_id = :operation_id', [
            'origin_type' => $originActor->type(),
            'origin_id' => $originActor->id(),
            'operation_id' => $id->toString(),
        ]);
    }

    private function captureFailure(DefaultOperationStatusQuery $query, OperationId $id): OperationStatusQueryException
    {
        try {
            $query->find($id);
            self::fail('Expected status query failure.');
        } catch (OperationStatusQueryException $exception) {
            return $exception;
        }
    }

    private function captureSourceCall(\Closure $call): OperationStatusSourceException
    {
        try {
            $call();
            self::fail('Expected source failure.');
        } catch (OperationStatusSourceException $exception) {
            return $exception;
        }
    }

    private function subject(OperationId $id, ?TenantRef $tenant): OperationStatusSubject
    {
        return $this->subjectForType($id, self::DEFERRED_TYPE, $tenant);
    }

    private function subjectForType(OperationId $id, string $type, ?TenantRef $tenant): OperationStatusSubject
    {
        return new OperationStatusSubject($id, $type, new ActorRef('origin-a', 'customer'), $tenant);
    }

    private function assertOwnKeyContexts(TenantStatusRecordingKeyProvider $keys, TenantRef $tenant): void
    {
        self::assertNotEmpty($keys->contexts);
        foreach ($keys->contexts as $context) {
            self::assertSame($tenant->type(), $context->tenant?->type());
            self::assertSame($tenant->id(), $context->tenant?->id());
        }
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString(sprintf('019f70ab-1000-7000-8000-%012d', $number));
    }

    private function tenant(string $id): TenantRef
    {
        return new TenantRef('customer', $id);
    }

    private function metadata(
        string $type,
        string $operation,
        string $strategy,
        string $outcome = TenantStatusProjectionOutcome::class,
    ): OperationMetadata {
        return new OperationMetadata($type, $operation, TenantStatusValue::class, $operation, $outcome, $strategy);
    }

    private function barrierConnection(
        ?\Closure $onBegin = null,
        ?\Closure $onJournalFetch = null,
    ): TenantStatusBarrierConnection {
        $base = $this->connection();
        $connection = new TenantStatusBarrierConnection(
            $base->getParams(),
            $base->getDriver(),
            $onBegin,
            $onJournalFetch,
        );
        $base->close();

        return $connection;
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

final readonly class TenantStatusValue implements OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class TenantStatusProjectionOutcome implements Outcome
{
    public function __construct(
        public string $status,
        public string $private,
    ) {}
}

final readonly class TenantStatusEphemeralOutcome implements EphemeralOutcome
{
    public function __construct(
        public string $private = 'ephemeral',
    ) {}
}

final readonly class TenantStatusDeferredOperation implements Operation {}

final readonly class TenantStatusInlineOperation implements Operation {}

final readonly class TenantStatusEphemeralOperation implements Operation {}

final class TenantStatusAuthorizer implements OperationStatusAuthorizer
{
    public int $calls = 0;

    public function __construct(
        private readonly OperationStatusAuthorizationDecision $decision,
    ) {}

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        ++$this->calls;

        return $this->decision;
    }
}

final class TenantStatusRecordingKeyProvider implements \BlackOps\StorageProtection\StorageKeyProvider
{
    private readonly ?Connection $observedConnection;

    /** @var list<\BlackOps\Internal\StorageProtection\StorageProtectionContext> */
    public array $contexts = [];

    /** @var list<array{active:bool,nesting:int,isolation:string,read_only:string}> */
    public array $keyObservations = [];

    public function __construct(?Connection $observedConnection = null)
    {
        $this->observedConnection = $observedConnection;
    }

    public function activeKey(
        ?TenantRef $tenant,
        \BlackOps\StorageProtection\StoragePurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        return $this->key('test-key', $tenant, $purpose);
    }

    public function key(
        string $keyId,
        ?TenantRef $tenant,
        \BlackOps\StorageProtection\StoragePurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        if ($this->observedConnection !== null) {
            $this->keyObservations[] = [
                'active' => $this->observedConnection->isTransactionActive(),
                'nesting' => $this->observedConnection->getTransactionNestingLevel(),
                'isolation' => (string) $this->observedConnection->fetchOne('SHOW transaction_isolation'),
                'read_only' => (string) $this->observedConnection->fetchOne('SHOW transaction_read_only'),
            ];
        }
        $this->contexts[] = new \BlackOps\Internal\StorageProtection\StorageProtectionContext(
            $purpose,
            'observed',
            'observed',
            'observed',
            1,
            $tenant,
        );

        return new \BlackOps\StorageProtection\StorageKey($keyId, str_repeat('k', times: 32));
    }
}

final class TenantStatusSqlRecordingConnection extends Connection
{
    /** @var list<array{method:string,sql:string,params:array<string,mixed>}> */
    public array $calls = [];

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->calls[] = ['method' => 'fetchAssociative', 'sql' => $query, 'params' => $params];

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->calls[] = ['method' => 'fetchOne', 'sql' => $query, 'params' => $params];

        return parent::fetchOne($query, $params, $types);
    }

    /** @return list<array<string,mixed>> */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->calls[] = ['method' => 'fetchAllAssociative', 'sql' => $query, 'params' => $params];

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        $this->calls[] = ['method' => 'iterateAssociative', 'sql' => $query, 'params' => $params];

        return parent::iterateAssociative($query, $params, $types);
    }
}

class TenantStatusBarrierConnection extends Connection
{
    public int $beginCalls = 0;

    public int $commitCalls = 0;

    public int $rollbackCalls = 0;

    /** @var list<bool> */
    public array $activeObservations = [];

    /** @var list<int> */
    public array $levelObservations = [];

    /** @var list<string> */
    public array $subjectIdentityObservations = [];

    public ?string $snapshotIsolation = null;

    public ?string $snapshotReadOnly = null;

    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly ?\Closure $onBegin,
        private readonly ?\Closure $onJournalFetch,
    ) {
        parent::__construct($params, $driver);
    }

    public function beginTransaction(): void
    {
        ++$this->beginCalls;
        parent::beginTransaction();
        if ($this->onBegin !== null) {
            ($this->onBegin)();
        }
    }

    public function commit(): void
    {
        ++$this->commitCalls;
        parent::commit();
    }

    public function rollBack(): void
    {
        ++$this->rollbackCalls;
        parent::rollBack();
    }

    public function getTransactionNestingLevel(): int
    {
        $level = parent::getTransactionNestingLevel();
        $this->levelObservations[] = $level;

        return $level;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $result = parent::fetchAssociative($query, $params, $types);
        if (
            str_contains($query, 'origin_actor_id')
            && is_array($result)
            && array_key_exists('origin_actor_id', $result)
        ) {
            $this->subjectIdentityObservations[] = (string) $result['origin_actor_id'];
        }

        return $result;
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        if ($this->onJournalFetch !== null && str_contains($query, 'encoded_record')) {
            $this->activeObservations[] = $this->isTransactionActive();
            $this->snapshotIsolation = (string) $this->fetchOne('SHOW transaction_isolation');
            $this->snapshotReadOnly = (string) $this->fetchOne('SHOW transaction_read_only');
            ($this->onJournalFetch)();
        }

        yield from parent::iterateAssociative($query, $params, $types);
    }
}

final class TenantStatusBeginFailureConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function beginTransaction(): void
    {
        ++$this->beginCalls;
        throw new \RuntimeException('begin-canary');
    }
}

final class TenantStatusBeginAfterStartFailureConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function beginTransaction(): void
    {
        parent::beginTransaction();
        throw new \RuntimeException('begin-after-start-canary');
    }
}

final class TenantStatusOwnershipInspectionConnection extends TenantStatusBarrierConnection
{
    public int $nestingInspections = 0;

    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function getTransactionNestingLevel(): int
    {
        ++$this->nestingInspections;
        if ($this->nestingInspections === 2) {
            throw new \RuntimeException('ownership-inspection-canary');
        }

        return parent::getTransactionNestingLevel();
    }
}

final class TenantStatusCommitFailureConnection extends TenantStatusBarrierConnection
{
    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly bool $throwAfterCommit,
    ) {
        parent::__construct($params, $driver, null, null);
    }

    public function commit(): void
    {
        if (!$this->throwAfterCommit) {
            ++$this->commitCalls;
            throw new \RuntimeException('commit-canary');
        }
        parent::commit();
        throw new \RuntimeException('commit-after-canary');
    }
}

final class TenantStatusRollbackFailureConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function rollBack(): void
    {
        ++$this->rollbackCalls;
        throw new \RuntimeException('rollback-canary');
    }
}

final class TenantStatusPostStateConnection extends TenantStatusBarrierConnection
{
    private bool $anomalous = false;

    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly string $mode,
    ) {
        parent::__construct($params, $driver, null, null);
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->mode === 'commit') {
            $this->anomalous = true;
        }
    }

    public function rollBack(): void
    {
        parent::rollBack();
        if ($this->mode === 'rollback') {
            $this->anomalous = true;
        }
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->anomalous ? 1 : parent::getTransactionNestingLevel();
    }
}

final class TenantStatusAmbiguousConnection extends TenantStatusBarrierConnection
{
    private bool $ambiguous = false;

    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function beginTransaction(): void
    {
        parent::beginTransaction();
        $this->ambiguous = true;
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->ambiguous ? 2 : parent::getTransactionNestingLevel();
    }
}

final class TenantStatusInspectionFailureConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function isAutoCommit(): bool
    {
        throw new \RuntimeException('inspection-canary');
    }
}

final class TenantStatusSubjectDriftConnection extends TenantStatusBarrierConnection
{
    private bool $drifted = false;

    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $result = parent::fetchAssociative($query, $params, $types);
        $this->drifted = true;

        return $result;
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->drifted ? 1 : parent::getTransactionNestingLevel();
    }
}

final class TenantStatusRecheckStorageConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        throw new \RuntimeException('subject-recheck-storage-canary');
    }
}

final class TenantStatusNonAutoCommitConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function isAutoCommit(): bool
    {
        return false;
    }
}

final class TenantStatusNoIoConnection extends TenantStatusBarrierConnection
{
    public function __construct(array $params, \Doctrine\DBAL\Driver $driver)
    {
        parent::__construct($params, $driver, null, null);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        throw new \RuntimeException('mismatch-issued-sql');
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        throw new \RuntimeException('mismatch-issued-sql');
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        throw new \RuntimeException('mismatch-issued-sql');
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        throw new \RuntimeException('mismatch-issued-sql');
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        throw new \RuntimeException('mismatch-issued-sql');
    }
}

final class TenantStatusFixtures
{
    public static function rejection(): \BlackOps\Core\Rejection\RejectionReason
    {
        return \BlackOps\Core\Rejection\RejectionReason::validation('validation_failed', [new \BlackOps\Core\Validation\Violation(
            'privateField',
            'not_blank',
            'private-violation',
        )]);
    }
}
