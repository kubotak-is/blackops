<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
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
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Status\DefaultOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusJournalValidator;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
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
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/** @mago-expect lint:too-many-methods */
final class PostgreSqlStatusQueryIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_p16_003_query';

    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $journal;
    private PostgreSqlOutcomeStore $outcomes;
    private OperationRegistry $registry;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA)->migrate();
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->journal->migrate();
        $this->outcomes = new PostgreSqlOutcomeStore($this->connection, self::SCHEMA);
        $this->registry = new OperationRegistry([
            $this->metadata('status.inline.completed', StatusInlineCompletedOperation::class, Inline::class),
            $this->metadata('status.deferred', StatusDeferredOperation::class, Deferred::class),
        ]);
    }

    public function testDenyDoesNotReadAnyDetailTable(): void
    {
        $id = $this->id(1);
        $this->enqueue($id);
        $this->connection->executeStatement('DROP TABLE ' . self::SCHEMA . '.outcomes');
        $this->connection->executeStatement('DROP TABLE ' . self::SCHEMA . '.dead_letters');
        $this->connection->executeStatement('DROP TABLE ' . self::SCHEMA . '.retention_purge_audits');

        $result = $this->query(OperationStatusAuthorizationDecision::deny())->find($id);

        self::assertInstanceOf(OperationStatusUnavailable::class, $result);
    }

    public function testProjectsInlineCompletedRejectedAndFailedFromCanonicalJournal(): void
    {
        $completedId = $this->id(10);
        $completedOutcome = new StatusProjectionOutcome('ready', 'outcome-private');
        $this->append($this->inlineCompleted($completedId, $completedOutcome));
        $rejectedId = $this->id(11);
        $this->append($this->inlineRejected($rejectedId));
        $failedId = $this->id(12);
        $this->append($this->inlineFailed($failedId));
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        $completed = $query->find($completedId);
        $rejected = $query->find($rejectedId);
        $failed = $query->find($failedId);

        self::assertInstanceOf(OperationStatusFound::class, $completed);
        self::assertSame(OperationStatusState::Completed, $completed->status()->state());
        self::assertEquals($completedOutcome, $completed->status()->outcome());
        self::assertInstanceOf(OperationStatusFound::class, $rejected);
        self::assertSame(OperationStatusState::Rejected, $rejected->status()->state());
        self::assertSame('validation', $rejected->status()->error()?->category());
        self::assertSame('validation_failed', $rejected->status()->error()?->code());
        self::assertSame(['category' => 'validation', 'code' => 'validation_failed'], [
            'category' => $rejected->status()->error()?->category(),
            'code' => $rejected->status()->error()?->code(),
        ]);
        self::assertInstanceOf(OperationStatusFound::class, $failed);
        self::assertSame(OperationStatusState::Failed, $failed->status()->state());
        self::assertSame('operation_failed', $failed->status()->error()?->code());
        self::assertStringNotContainsString('private', json_encode(
            [
                $rejected->status()->error()?->category(),
                $rejected->status()->error()?->code(),
                $failed->status()->error()?->code(),
            ],
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testProjectsAllDeferredStatesAndMapsSupervisingToRunning(): void
    {
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
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        foreach ($states as $index => $state) {
            $id = $this->id(100 + $index);
            $this->createDeferred($id, $state);
            if ($state === LifecycleState::RetryScheduled) {
                $stored = new PostgreSqlStatusReader($this->connection, self::SCHEMA)->deferredState($id);
                self::assertNotNull($stored);
                $records = iterator_to_array($this->journal->records($id), false);
                $validated = new OperationStatusJournalValidator()->validate($id, $records);
                self::assertSame(LifecycleState::RetryScheduled, $validated->state);
                self::assertEquals($stored->availableAt, $validated->retryAt);
                self::assertSame(1, $validated->lastAttempt()?->number);
            }
            try {
                $result = $query->find($id);
            } catch (OperationStatusQueryException $exception) {
                self::fail($state->value . ': ' . $exception->queryCode());
            }

            self::assertInstanceOf(OperationStatusFound::class, $result, $state->value);
            self::assertSame($expected[$index], $result->status()->state(), $state->value);
            if (in_array($state, [LifecycleState::Running, LifecycleState::Supervising], true)) {
                self::assertSame(1, $result->status()->attempt());
            }
            if ($state === LifecycleState::RetryScheduled) {
                self::assertSame(1, $result->status()->attempt());
                self::assertSame(
                    '2026-07-19T00:01:00.143069+00:00',
                    $result->status()->retryAt()?->format('Y-m-d\TH:i:s.uP'),
                );
            }
            if ($state === LifecycleState::Completed) {
                self::assertInstanceOf(StatusProjectionOutcome::class, $result->status()->outcome());
            }
            if ($state === LifecycleState::Rejected) {
                self::assertSame('validation_failed', $result->status()->error()?->code());
            }
            if ($state === LifecycleState::DeadLettered) {
                self::assertSame('operation_dead_lettered', $result->status()->error()?->code());
            }
        }
    }

    public function testRetryScheduledTimestampMismatchStillFailsIntegrity(): void
    {
        $id = $this->id(189);
        $this->createDeferred($id, LifecycleState::RetryScheduled);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET available_at = :available_at
            WHERE operation_id = :operation_id',
            [
                'available_at' => '2026-07-19T00:01:00.143070Z',
                'operation_id' => $id->toString(),
            ],
        );

        $exception = $this->captureFailure($this->query(OperationStatusAuthorizationDecision::allow()), $id);

        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
        self::assertSame('status_query.integrity_failed', $exception->getMessage());
    }

    public function testExecutionActorChangesRemainFoundAcrossRetryAndCompletion(): void
    {
        $retryId = $this->id(190);
        $completedId = $this->id(191);
        $this->createDeferredWithActorChanges($retryId, LifecycleState::RetryScheduled);
        $this->createDeferredWithActorChanges($completedId, LifecycleState::Completed);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        $retry = $query->find($retryId);
        $completed = $query->find($completedId);

        self::assertInstanceOf(OperationStatusFound::class, $retry);
        self::assertSame(OperationStatusState::RetryScheduled, $retry->status()->state());
        self::assertSame(1, $retry->status()->attempt());
        self::assertInstanceOf(OperationStatusFound::class, $completed);
        self::assertSame(OperationStatusState::Completed, $completed->status()->state());
        self::assertInstanceOf(StatusProjectionOutcome::class, $completed->status()->outcome());

        $validated = new OperationStatusJournalValidator()->validate($completedId, iterator_to_array(
            $this->journal->records($completedId),
            false,
        ));
        self::assertCount(2, $validated->attempts);
        self::assertSame(2, $validated->lastAttempt()?->number);
    }

    public function testDetailUsesTheSameConnectionInsideARepeatableReadOnlySnapshot(): void
    {
        $id = $this->id(180);
        $this->createDeferred($id, LifecycleState::Completed);
        StatusProjectionObservation::$observer = $this->connection;
        StatusProjectionObservation::$activeTransactions = [];

        try {
            $result = $this->query(OperationStatusAuthorizationDecision::allow())->find($id);
        } finally {
            StatusProjectionObservation::$observer = null;
        }

        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertGreaterThanOrEqual(2, count(StatusProjectionObservation::$activeTransactions));
        self::assertNotContains(false, StatusProjectionObservation::$activeTransactions);
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testDetailRefusesToPiggybackOnAnUncontrolledTransactionSnapshot(): void
    {
        $id = $this->id(181);
        $this->createDeferred($id, LifecycleState::Accepted);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());
        $this->connection->beginTransaction();

        try {
            $exception = $this->captureFailure($query, $id);
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testRetentionReturnsExpiredOnlyAfterAllowForOutcomeAndRejectedJournal(): void
    {
        $completedId = $this->id(200);
        $this->createDeferred($completedId, LifecycleState::Completed);
        $this->deleteOutcomeAndAudit($completedId);
        $rejectedId = $this->id(201);
        $this->createDeferred($rejectedId, LifecycleState::Rejected);
        $this->deleteJournalAndAudit($rejectedId);

        $allow = $this->query(OperationStatusAuthorizationDecision::allow());
        self::assertInstanceOf(OperationStatusExpired::class, $allow->find($completedId));
        self::assertInstanceOf(OperationStatusExpired::class, $allow->find($rejectedId));

        $deny = $this->query(OperationStatusAuthorizationDecision::deny());
        self::assertInstanceOf(OperationStatusUnavailable::class, $deny->find($completedId));
        self::assertInstanceOf(OperationStatusUnavailable::class, $deny->find($rejectedId));
    }

    public function testJournalRetentionKeepsFailedAndDeadLetteredWithSafeFixedCodes(): void
    {
        $failedId = $this->id(210);
        $this->createDeferred($failedId, LifecycleState::Failed);
        $this->deleteJournalAndAudit($failedId);
        $deadId = $this->id(211);
        $this->createDeferred($deadId, LifecycleState::DeadLettered);
        $this->deleteJournalAndAudit($deadId);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.dead_letters WHERE operation_id = :operation_id', ['operation_id' => $deadId->toString()]);
        $this->insertAudit($deadId, RetentionPurgeTarget::DeadLetter);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        $failed = $query->find($failedId);
        $dead = $query->find($deadId);

        self::assertInstanceOf(OperationStatusFound::class, $failed);
        self::assertSame('operation_failed', $failed->status()->error()?->code());
        self::assertInstanceOf(OperationStatusFound::class, $dead);
        self::assertSame('operation_dead_lettered', $dead->status()->error()?->code());
    }

    public function testMissingOrConflictingOutcomeAndDeadLetterEvidenceFailsIntegrity(): void
    {
        $missingOutcome = $this->id(220);
        $this->createDeferred($missingOutcome, LifecycleState::Completed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $missingOutcome->toString()]);
        $bothOutcome = $this->id(221);
        $this->createDeferred($bothOutcome, LifecycleState::Completed);
        $this->insertAudit($bothOutcome, RetentionPurgeTarget::Outcome);
        $bothDead = $this->id(222);
        $this->createDeferred($bothDead, LifecycleState::DeadLettered);
        $this->insertAudit($bothDead, RetentionPurgeTarget::DeadLetter);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        foreach ([$missingOutcome, $bothOutcome, $bothDead] as $id) {
            $exception = $this->captureFailure($query, $id);
            self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
            self::assertSame('status_query.integrity_failed', $exception->getMessage());
            self::assertFalse($this->connection->isTransactionActive());
        }
    }

    public function testOutcomeTypeSequenceStateAndNonTerminalRetentionDriftFailIntegrity(): void
    {
        $wrongOutcome = $this->id(230);
        $this->createDeferred($wrongOutcome, LifecycleState::Completed);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $wrongOutcome->toString()]);
        $this->outcomes->save(
            new OutcomeRecord(
                $wrongOutcome,
                new WrongStatusProjectionOutcome('wrong'),
                new DateTimeImmutable('2026-07-19T00:00:10Z'),
            ),
        );
        $sequenceGap = $this->id(231);
        $this->createDeferred($sequenceGap, LifecycleState::Running);
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id AND sequence = 2', ['operation_id' => $sequenceGap->toString()]);
        $purgedAccepted = $this->id(232);
        $this->createDeferred($purgedAccepted, LifecycleState::Accepted);
        $this->deleteJournalAndAudit($purgedAccepted);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        foreach ([$wrongOutcome, $sequenceGap, $purgedAccepted] as $id) {
            self::assertSame(
                OperationStatusQueryException::INTEGRITY_FAILED,
                $this->captureFailure($query, $id)->queryCode(),
            );
        }
    }

    public function testInvalidSubjectTypeAndCanonicalIdentifierUseIntegrityOrDecodeInsteadOfStorage(): void
    {
        $invalidType = $this->id(235);
        $this->enqueue($invalidType);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET operation_type = :operation_type
            WHERE operation_id = :operation_id',
            [
                'operation_type' => 'Invalid Type',
                'operation_id' => $invalidType->toString(),
            ],
        );
        $invalidIdentifier = $this->id(236);
        $this->createDeferred($invalidIdentifier, LifecycleState::Accepted);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . ".journal
            SET encoded_record = convert_to(
                jsonb_set(
                    convert_from(encoded_record, 'UTF8')::jsonb,
                    '{operation,id}',
                    to_jsonb('invalid-identifier'::text)
                )::text,
                'UTF8'
            )
            WHERE operation_id = :operation_id",
            ['operation_id' => $invalidIdentifier->toString()],
        );
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        self::assertSame(
            OperationStatusQueryException::INTEGRITY_FAILED,
            $this->captureFailure($query, $invalidType)->queryCode(),
        );
        self::assertSame(
            OperationStatusQueryException::DECODE_FAILED,
            $this->captureFailure($query, $invalidIdentifier)->queryCode(),
        );
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testUnsupportedCompletedOutcomeSchemaIsDecodeFailureAndCleansUpSnapshot(): void
    {
        $id = $this->id(237);
        $this->createDeferred($id, LifecycleState::Completed);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.outcomes
            SET schema_version = 99
            WHERE operation_id = :operation_id',
            ['operation_id' => $id->toString()],
        );

        $exception = $this->captureFailure($this->query(OperationStatusAuthorizationDecision::allow()), $id);

        self::assertSame(OperationStatusQueryException::DECODE_FAILED, $exception->queryCode());
        self::assertSame('status_query.decode_failed', $exception->getMessage());
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testJournalOriginActorChangedAfterAuthorizationFailsIntegrityAndCleansUpSnapshot(): void
    {
        $id = $this->id(238);
        $this->createDeferred($id, LifecycleState::Failed);
        $authorizer = new StatusProjectionMutatingAuthorizer($this->connection, $id, self::SCHEMA);
        $query = new DefaultOperationStatusQuery(
            new PostgreSqlOperationStatusSource($this->connection, $this->registry, self::SCHEMA),
            $authorizer,
        );

        $exception = $this->captureFailure($query, $id);

        self::assertSame('origin-private', $authorizer->request?->originActor()?->id());
        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
        self::assertSame('status_query.integrity_failed', $exception->getMessage());
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testTransportPayloadTombstoneDoesNotExpireTerminalStatus(): void
    {
        $id = $this->id(240);
        $this->createDeferred($id, LifecycleState::Failed);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET encoded_payload = NULL,
                encoded_context = NULL,
                payload_purged_at = :purged_at
            WHERE operation_id = :operation_id',
            [
                'purged_at' => '2026-07-19T02:00:00Z',
                'operation_id' => $id->toString(),
            ],
        );
        $this->insertAudit($id, RetentionPurgeTarget::TransportPayload);

        $result = $this->query(OperationStatusAuthorizationDecision::allow())->find($id);

        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertSame(OperationStatusState::Failed, $result->status()->state());
    }

    public function testUnknownAndFullyPurgedIdentityRemainUnavailable(): void
    {
        $unknown = $this->id(250);
        $purged = $this->id(251);
        $this->append($this->inlineFailed($purged));
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id', ['operation_id' => $purged->toString()]);
        $this->insertAudit($purged, RetentionPurgeTarget::Journal);
        $query = $this->query(OperationStatusAuthorizationDecision::allow());

        self::assertInstanceOf(OperationStatusUnavailable::class, $query->find($unknown));
        self::assertInstanceOf(OperationStatusUnavailable::class, $query->find($purged));
    }

    private function query(OperationStatusAuthorizationDecision $decision): DefaultOperationStatusQuery
    {
        return new DefaultOperationStatusQuery(
            new PostgreSqlOperationStatusSource($this->connection, $this->registry, self::SCHEMA),
            new StatusProjectionAuthorizer($decision),
        );
    }

    private function createDeferred(OperationId $id, LifecycleState $state): void
    {
        $this->enqueue($id);
        $records = $this->deferredRecords($id, $state);
        $this->append($records);
        $attempt = $state === LifecycleState::Accepted ? 0 : 1;
        $active = $state === LifecycleState::Running;
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                next_sequence = :next_sequence,
                attempt_number = :attempt_number,
                current_attempt_id = :current_attempt_id,
                current_attempt_started_at = :current_attempt_started_at,
                available_at = :available_at
            WHERE operation_id = :operation_id',
            [
                'state' => $state->value,
                'next_sequence' => count($records) + 1,
                'attempt_number' => $attempt,
                'current_attempt_id' => $active ? $this->attemptId($id)->toString() : null,
                'current_attempt_started_at' => $active ? '2026-07-19T00:00:03Z' : null,
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
                    new StatusProjectionOutcome('ready', 'stored-private'),
                    new DateTimeImmutable('2026-07-19T00:00:06Z'),
                ),
            );
        }
        if ($state === LifecycleState::DeadLettered) {
            $this->insertDeadLetter($id);
        }
    }

    private function createDeferredWithActorChanges(OperationId $id, LifecycleState $state): void
    {
        $this->enqueue($id);
        $records = $this->deferredActorContinuityRecords($id, $state);
        $this->append($records);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                next_sequence = :next_sequence,
                attempt_number = :attempt_number,
                available_at = :available_at
            WHERE operation_id = :operation_id',
            [
                'state' => $state->value,
                'next_sequence' => count($records) + 1,
                'attempt_number' => $state === LifecycleState::Completed ? 2 : 1,
                'available_at' => $state === LifecycleState::RetryScheduled
                    ? '2026-07-19T00:01:00Z'
                    : '2026-07-19T00:00:00Z',
                'operation_id' => $id->toString(),
            ],
        );

        if ($state === LifecycleState::Completed) {
            $this->outcomes->save(
                new OutcomeRecord(
                    $id,
                    new StatusProjectionOutcome('ready', 'stored-private'),
                    new DateTimeImmutable('2026-07-19T00:00:08Z'),
                ),
            );
        }
    }

    /** @return list<JournalRecord> */
    private function deferredActorContinuityRecords(OperationId $id, LifecycleState $state): array
    {
        $first = $this->attempt($id);
        $second = new JournalAttempt(
            AttemptId::fromString('019f70ab-9000-7000-8000-' . substr($id->toString(), -12)),
            2,
            new DateTimeImmutable('2026-07-19T00:00:06Z'),
        );
        $records = [
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StatusProjectionValue('value-private')),
                executionId: 'http-user',
                authorizationId: 'authorization-private',
            ),
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                2,
                JournalEvent::OperationAccepted,
                executionId: 'http-user',
                authorizationId: 'authorization-private',
            ),
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                3,
                JournalEvent::AttemptStarted,
                attempt: $first,
                executionId: 'worker-one',
                authorizationId: 'authorization-private',
            ),
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                4,
                JournalEvent::AttemptFailed,
                new AttemptFailedData('PrivateFailure', 'attempt-private', true),
                $first,
                'worker-one',
                'origin-private',
                'authorization-private',
            ),
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData($first->id, 2, new DateTimeImmutable('2026-07-19T00:01:00Z'), 60_000),
                $first,
                'worker-recovery',
                'origin-private',
                'authorization-private',
            ),
        ];
        if ($state === LifecycleState::RetryScheduled) {
            return $records;
        }

        $records[] = $this->record(
            $id,
            'status.deferred',
            'deferred',
            6,
            JournalEvent::AttemptStarted,
            attempt: $second,
            executionId: 'worker-two',
            authorizationId: 'authorization-private',
        );
        $records[] = $this->record(
            $id,
            'status.deferred',
            'deferred',
            7,
            JournalEvent::AttemptSucceeded,
            attempt: $second,
            executionId: 'worker-three',
            authorizationId: 'authorization-private',
        );
        $records[] = $this->record(
            $id,
            'status.deferred',
            'deferred',
            8,
            JournalEvent::OperationCompleted,
            new OperationCompletedData(new StatusProjectionOutcome('ready', 'journal-private')),
            $second,
            'worker-three',
            'origin-private',
            'authorization-private',
        );

        return $records;
    }

    /** @return list<JournalRecord> */
    private function deferredRecords(OperationId $id, LifecycleState $state): array
    {
        $attempt = $this->attempt($id);
        $records = [
            $this->record(
                $id,
                'status.deferred',
                'deferred',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StatusProjectionValue('value-private')),
            ),
            $this->record($id, 'status.deferred', 'deferred', 2, JournalEvent::OperationAccepted),
        ];
        if ($state === LifecycleState::Accepted) {
            return $records;
        }
        $records[] = $this->record(
            $id,
            'status.deferred',
            'deferred',
            3,
            JournalEvent::AttemptStarted,
            attempt: $attempt,
        );
        if ($state === LifecycleState::Running) {
            return $records;
        }
        if (in_array($state, [LifecycleState::Completed, LifecycleState::Rejected], true)) {
            $records[] = $state === LifecycleState::Completed
                ? $this->record(
                    $id,
                    'status.deferred',
                    'deferred',
                    4,
                    JournalEvent::AttemptSucceeded,
                    attempt: $attempt,
                )
                : $this->record(
                    $id,
                    'status.deferred',
                    'deferred',
                    4,
                    JournalEvent::OperationRejected,
                    new OperationRejectedData($this->rejection()),
                    $attempt,
                );
            if ($state === LifecycleState::Completed) {
                $records[] = $this->record(
                    $id,
                    'status.deferred',
                    'deferred',
                    5,
                    JournalEvent::OperationCompleted,
                    new OperationCompletedData(new StatusProjectionOutcome('ready', 'journal-private')),
                    $attempt,
                );
            }

            return $records;
        }

        $records[] = $this->record(
            $id,
            'status.deferred',
            'deferred',
            4,
            JournalEvent::AttemptFailed,
            new AttemptFailedData('PrivateFailure', 'attempt-private', true),
            $attempt,
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
            LifecycleState::Failed => new OperationFailedData('PrivateFailure', 'failure-private', false),
            LifecycleState::DeadLettered => new OperationDeadLetteredData(
                $attempt->id,
                1,
                'PrivateFailure',
                'dead-private',
                new DateTimeImmutable('2026-07-19T00:00:05.654321Z'),
            ),
            default => throw new \LogicException('Unexpected deferred state fixture.'),
        };
        $event = match ($state) {
            LifecycleState::RetryScheduled => JournalEvent::AttemptRetryScheduled,
            LifecycleState::Failed => JournalEvent::OperationFailed,
            LifecycleState::DeadLettered => JournalEvent::OperationDeadLettered,
            default => throw new \LogicException('Unexpected deferred state fixture.'),
        };
        $records[] = $this->record($id, 'status.deferred', 'deferred', 5, $event, $terminal, $attempt);

        return $records;
    }

    /** @return list<JournalRecord> */
    private function inlineCompleted(OperationId $id, Outcome $outcome): array
    {
        $attempt = $this->attempt($id);

        return [
            $this->record(
                $id,
                'status.inline.completed',
                'inline',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StatusProjectionValue('value-private')),
            ),
            $this->record($id, 'status.inline.completed', 'inline', 2, JournalEvent::AttemptStarted, attempt: $attempt),
            $this->record(
                $id,
                'status.inline.completed',
                'inline',
                3,
                JournalEvent::AttemptSucceeded,
                attempt: $attempt,
            ),
            $this->record(
                $id,
                'status.inline.completed',
                'inline',
                4,
                JournalEvent::OperationCompleted,
                new OperationCompletedData($outcome),
                $attempt,
            ),
        ];
    }

    /** @return list<JournalRecord> */
    private function inlineRejected(OperationId $id): array
    {
        return [
            $this->record(
                $id,
                'status.inline.rejected',
                'inline',
                1,
                JournalEvent::OperationRejected,
                new OperationRejectedData($this->rejection()),
            ),
        ];
    }

    /** @return list<JournalRecord> */
    private function inlineFailed(OperationId $id): array
    {
        return [
            $this->record(
                $id,
                'status.inline.failed',
                'inline',
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StatusProjectionValue('value-private')),
            ),
            $this->record(
                $id,
                'status.inline.failed',
                'inline',
                2,
                JournalEvent::OperationFailed,
                new OperationFailedData('PrivateFailure', 'failure-private', false),
            ),
        ];
    }

    private function record(
        OperationId $id,
        string $type,
        string $strategy,
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        ?JournalAttempt $attempt = null,
        string $executionId = 'execution-private',
        string $originId = 'origin-private',
        ?string $authorizationId = null,
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
                $strategy,
                CorrelationId::fromString('019f70ab-6000-7000-8000-' . substr($id->toString(), -12)),
                actorContext: new ActorContext(
                    new ActorRef($originId, 'customer'),
                    $authorizationId === null ? null : new ActorRef($authorizationId, 'customer'),
                    new ActorRef($executionId, 'worker'),
                ),
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    /** @param list<JournalRecord> $records */
    private function append(array $records): void
    {
        foreach ($records as $record) {
            $this->journal->append($record);
        }
    }

    private function enqueue(OperationId $id): void
    {
        new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA)->enqueue(
            new DeferredOperationMessage(
                $id,
                'status.deferred',
                1,
                '{"private":"payload"}',
                '{"private":"context"}',
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
            ),
        );
    }

    private function insertDeadLetter(OperationId $id): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.dead_letters (
            operation_id, final_attempt_id, final_attempt_number, reason_type, reason_message, moved_at
        ) VALUES (
            :operation_id, :attempt_id, 1, :reason_type, :reason_message, :moved_at
        )', [
            'operation_id' => $id->toString(),
            'attempt_id' => $this->attemptId($id)->toString(),
            'reason_type' => 'PrivateFailure',
            'reason_message' => 'dead-private',
            'moved_at' => '2026-07-19T00:00:05.654321Z',
        ]);
    }

    private function deleteOutcomeAndAudit(OperationId $id): void
    {
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => $id->toString()]);
        $this->insertAudit($id, RetentionPurgeTarget::Outcome);
    }

    private function deleteJournalAndAudit(OperationId $id): void
    {
        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id', ['operation_id' => $id->toString()]);
        $this->insertAudit($id, RetentionPurgeTarget::Journal);
    }

    private function insertAudit(OperationId $id, RetentionPurgeTarget $target): void
    {
        static $audit = 1;
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_purge_audits (
            audit_id, operation_id, target, affected_count, policy, purged_at, purged_by
        ) VALUES (
            :audit_id, :operation_id, :target, 1, :policy, :purged_at, :actor
        )', [
            'audit_id' => sprintf('019f70ab-7000-7000-8000-%012d', $audit++),
            'operation_id' => $id->toString(),
            'target' => $target->value,
            'policy' => 'private-policy',
            'purged_at' => '2026-07-19T02:00:00Z',
            'actor' => 'private-actor',
        ]);
    }

    private function rejection(): RejectionReason
    {
        return RejectionReason::validation('validation_failed', [new Violation(
            'privateField',
            'not_blank',
            'private_violation',
        )]);
    }

    private function attempt(OperationId $id): JournalAttempt
    {
        return new JournalAttempt($this->attemptId($id), 1, new DateTimeImmutable('2026-07-19T00:00:03Z'));
    }

    private function attemptId(OperationId $id): AttemptId
    {
        return AttemptId::fromString('019f70ab-8000-7000-8000-' . substr($id->toString(), -12));
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString(sprintf('019f70ab-1000-7000-8000-%012d', $number));
    }

    private function metadata(string $type, string $operation, string $strategy): OperationMetadata
    {
        return new OperationMetadata(
            $type,
            $operation,
            StatusProjectionValue::class,
            $operation,
            StatusProjectionOutcome::class,
            $strategy,
        );
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

final readonly class StatusProjectionAuthorizer implements OperationStatusAuthorizer
{
    public function __construct(
        private OperationStatusAuthorizationDecision $decision,
    ) {}

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        return $this->decision;
    }
}

final class StatusProjectionMutatingAuthorizer implements OperationStatusAuthorizer
{
    public ?OperationStatusAuthorizationRequest $request = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly OperationId $operationId,
        private readonly string $schema,
    ) {}

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        $this->request = $request;
        $this->connection->executeStatement(
            'UPDATE ' . $this->schema . ".journal
            SET encoded_record = convert_to(
                jsonb_set(
                    convert_from(encoded_record, 'UTF8')::jsonb,
                    '{operation,actors,origin,id}',
                    to_jsonb(CAST(:actor_id AS text))
                )::text,
                'UTF8'
            )
            WHERE operation_id = :operation_id",
            [
                'actor_id' => 'different-origin',
                'operation_id' => $this->operationId->toString(),
            ],
        );

        return OperationStatusAuthorizationDecision::allow();
    }
}

final readonly class StatusProjectionValue implements OperationValue
{
    public function __construct(
        public string $private,
    ) {}
}

final readonly class StatusProjectionOutcome implements Outcome
{
    public function __construct(
        public string $status,
        public string $private,
    ) {
        if (StatusProjectionObservation::$observer !== null) {
            StatusProjectionObservation::$activeTransactions[] =
                StatusProjectionObservation::$observer->isTransactionActive();
        }
    }
}

final class StatusProjectionObservation
{
    public static ?Connection $observer = null;

    /** @var list<bool> */
    public static array $activeTransactions = [];
}

final readonly class WrongStatusProjectionOutcome implements Outcome
{
    public function __construct(
        public string $private,
    ) {}
}

final readonly class StatusInlineCompletedOperation implements Operation {}

final readonly class StatusDeferredOperation implements Operation {}
