<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Core\Supervision\SupervisionDecision;
use BlackOps\Core\Supervision\SupervisionPolicy;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\ClaimExecutionGuard;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredLeaseExpiredRecovery;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\SupervisedHandlerFailureException;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Outcome\OutcomeWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class DeferredWorkerRuntimeTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_010';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687731';
    private const CORRELATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687732';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlDeferredOperationReceiver $receiver;
    private PostgreSqlCanonicalJournalStore $journal;
    private PostgreSqlOutcomeStore $outcomes;
    private ReflectionJsonOperationCodec $codec;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->receiver = new PostgreSqlDeferredOperationReceiver($this->connection, self::SCHEMA, 'worker-a', 30);
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->outcomes = new PostgreSqlOutcomeStore($this->connection, self::SCHEMA);
        $this->codec = new ReflectionJsonOperationCodec();
        $this->sender->migrate();
        $this->receiver->migrate();
        $this->journal->migrate();
    }

    public function testWorkerRunsClaimedOperationToCompletion(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $result = $this->runtime($handler)->run($claim);

        $row = $this->operationRow();
        $records = $this->records();

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(WorkerReportDone::class, $result->outcome());
        self::assertSame('done-weekly', $result->outcome()->message);
        self::assertSame('completed', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4, 5], array_column($records, 'sequence'));
        self::assertNotNull($records[2]->attempt);
        self::assertSame(1, $records[2]->attempt?->number);
        $storedOutcome = $this->outcomes->find(OperationId::fromString(self::OPERATION_ID));
        self::assertNotNull($storedOutcome);
        self::assertInstanceOf(WorkerReportDone::class, $storedOutcome->outcome());
        self::assertSame('done-weekly', $storedOutcome->outcome()->message);
    }

    public function testWorkerUsesContainerResolvedSelfHandledOperationWithRequiredDependency(): void
    {
        $handler = new RequiredSelfHandledWorkerOperation(new WorkerOperationDependency('resolved'));
        $metadata = $this->selfHandledMetadata();
        $this->accept($metadata, $handler);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, metadata: $metadata)->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame('resolved', $handler->handledWith);
    }

    public function testLeaseRecoveryUsesContainerResolvedSelfHandledOperationWithRequiredDependency(): void
    {
        $handler = new RequiredSelfHandledWorkerOperation(new WorkerOperationDependency('recovery'));
        $metadata = $this->selfHandledMetadata();
        $this->accept($metadata, $handler);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $this->startAttemptWithoutCompleting($claim, $metadata, $handler);

        self::assertTrue($this->recovery($metadata, $handler)->recoverOne(
            new DateTimeImmutable('2026-07-10T00:02:00.000000Z'),
        ));
    }

    public function testWorkerRecordsBusinessRejection(): void
    {
        $handler = new RejectingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $result = $this->runtime($handler)->run($claim);

        $row = $this->operationRow();
        $records = $this->records();

        self::assertTrue($result->isRejected());
        self::assertSame('rejected', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(5, (int) $row['next_sequence']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::OperationRejected,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4], array_column($records, 'sequence'));
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testWorkerRecordsOperationFailureAndWrapsNonRetryableHandlerException(): void
    {
        $handler = new ThrowingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler)->run($claim);
            self::fail('Expected handler exception to be rethrown.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertSame('boom', $exception->getPrevious()->getMessage());
        }

        $row = $this->operationRow();
        $records = $this->records();

        self::assertSame('failed', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4, 5], array_column($records, 'sequence'));
        self::assertInstanceOf(AttemptFailedData::class, $records[3]->data);
        self::assertSame(RuntimeException::class, $records[3]->data->errorType);
        self::assertSame('boom', $records[3]->data->errorMessage);
        self::assertFalse($records[3]->data->retryable);
        self::assertInstanceOf(OperationFailedData::class, $records[4]->data);
        self::assertSame(RuntimeException::class, $records[4]->data->errorType);
        self::assertSame('boom', $records[4]->data->errorMessage);
        self::assertFalse($records[4]->data->retryable);
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testWorkerSchedulesRetryAndWrapsRetryableHandlerException(): void
    {
        $handler = new RetryableThrowingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler)->run($claim);
            self::fail('Expected handler exception to be rethrown.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(RetryableWorkerRuntimeException::class, $exception->getPrevious());
            self::assertSame('temporary boom', $exception->getPrevious()->getMessage());
        }

        $row = $this->operationRow();
        $records = $this->records();

        self::assertSame('retry_scheduled', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::AttemptRetryScheduled,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4, 5], array_column($records, 'sequence'));
        self::assertInstanceOf(AttemptFailedData::class, $records[3]->data);
        self::assertSame(RetryableWorkerRuntimeException::class, $records[3]->data->errorType);
        self::assertSame('temporary boom', $records[3]->data->errorMessage);
        self::assertTrue($records[3]->data->retryable);
        self::assertInstanceOf(AttemptRetryScheduledData::class, $records[4]->data);
        self::assertSame($records[3]->attempt?->id->toString(), $records[4]->data->failedAttemptId->toString());
        self::assertSame(2, $records[4]->data->nextAttemptNumber);
        self::assertSame('2026-07-10T00:02:01+00:00', $records[4]->data->scheduledAt->format(DATE_ATOM));
        self::assertSame(1_000, $records[4]->data->delayMilliseconds);
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testSignalHeartbeatClaimLossSkipsFailureSupervisionAndCompletionUpdates(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(new ClaimLostWorkerReportHandler())->run($claim);
            self::fail('Expected claim loss interruption.');
        } catch (WorkerClaimLostException $exception) {
            self::assertSame('claim lost', $exception->getMessage());
        }

        $row = $this->operationRow();
        $records = $this->records();
        self::assertSame('running', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(4, (int) $row['next_sequence']);
        self::assertNotNull($row['lease_owner']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted, JournalEvent::AttemptStarted],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
    }

    public function testSignalHeartbeatGuardWrapsOnlyHandlerBetweenAttemptAndCompletionTransactions(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);
        $guard = new ObservingWorkerClaimExecutionGuard($this->connection, self::SCHEMA);

        $result = $this->runtime(new CompletingWorkerReportHandler(), guard: $guard)->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(['running', 'running'], $guard->states);
        self::assertSame([3, 3], $guard->journalCounts);
        self::assertSame('completed', $this->operationRow()['state']);
        self::assertCount(5, $this->records());
    }

    public function testOutcomeSaveFailureRollsBackCompletedStateAndJournal(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(new CompletingWorkerReportHandler(), outcomes: new FailingWorkerOutcomeWriter())->run(
                $claim,
            );
            self::fail('Expected outcome save failure.');
        } catch (OutcomeStoreException $exception) {
            self::assertSame('outcome unavailable', $exception->getMessage());
        }

        $row = $this->operationRow();
        self::assertSame('running', $row['state']);
        self::assertSame(4, (int) $row['next_sequence']);
        self::assertNotNull($row['current_attempt_id']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted, JournalEvent::AttemptStarted],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $this->records()),
        );
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testLeaseExpiredRecoveryRecordsAttemptFailureAndSchedulesRetry(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $this->startAttemptWithoutCompleting($claim);

        $recovered = $this->recovery()->recoverOne(new DateTimeImmutable('2026-07-10T00:02:00.000000Z'));
        $row = $this->operationRow();
        $records = $this->records();

        self::assertTrue($recovered);
        self::assertSame('retry_scheduled', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertNull($row['current_attempt_id']);
        self::assertNull($row['current_attempt_started_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::AttemptRetryScheduled,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertInstanceOf(AttemptFailedData::class, $records[3]->data);
        self::assertSame('lease_expired', $records[3]->data->errorType);
        self::assertSame('Deferred operation lease expired.', $records[3]->data->errorMessage);
        self::assertTrue($records[3]->data->retryable);
        self::assertInstanceOf(AttemptRetryScheduledData::class, $records[4]->data);
        self::assertSame($records[2]->attempt?->id->toString(), $records[4]->data->failedAttemptId->toString());
        self::assertSame(2, $records[4]->data->nextAttemptNumber);
        self::assertSame('2026-07-10T00:02:01+00:00', $records[4]->data->scheduledAt->format(DATE_ATOM));
    }

    public function testWorkerDeadLettersOperationWithoutOperationFailedEvent(): void
    {
        $handler = new ThrowingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler, new AlwaysDeadLetterSupervisionPolicy())->run($claim);
            self::fail('Expected handler exception to be rethrown.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertSame('boom', $exception->getPrevious()->getMessage());
        }

        $row = $this->operationRow();
        $deadLetter = $this->deadLetterRow();
        $records = $this->records();

        self::assertSame('dead_lettered', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertSame(self::OPERATION_ID, $deadLetter['operation_id']);
        self::assertSame($records[3]->attempt?->id->toString(), $deadLetter['final_attempt_id']);
        self::assertSame(1, (int) $deadLetter['final_attempt_number']);
        self::assertSame(RuntimeException::class, $deadLetter['reason_type']);
        self::assertSame('boom', $deadLetter['reason_message']);
        self::assertSame('2026-07-10T00:02:00.000000Z', $deadLetter['moved_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationDeadLettered,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertInstanceOf(OperationDeadLetteredData::class, $records[4]->data);
        self::assertSame($records[3]->attempt?->id->toString(), $records[4]->data->finalAttemptId?->toString());
        self::assertSame(1, $records[4]->data->finalAttemptNumber);
        self::assertSame(RuntimeException::class, $records[4]->data->reasonType);
        self::assertSame('boom', $records[4]->data->reasonMessage);
        self::assertSame('2026-07-10T00:02:00+00:00', $records[4]->data->movedAt->format(DATE_ATOM));
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testFailureReservationRejectsStaleFencingToken(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $stale = new OperationClaim($claim->message(), self::OPERATION_ID . ':999');

        $this->expectException(DeferredTransportException::class);

        new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA)->reserveFailed(
            $stale,
            new DateTimeImmutable('2026-07-10T00:02:00.000000Z'),
        );
    }

    private function accept(?OperationMetadata $metadata = null, ?Operation $definition = null): void
    {
        $metadata ??= $this->metadata();
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
        );
        $value = new WorkerReportValue('weekly');
        $encoded = $this->codec->encode($metadata, $value, $context);
        $envelope = new OperationEnvelope($definition ?? new WorkerReportOperation(), $value, $context, new Deferred());
        $identifiers = new IdentifierFactory(new DeferredWorkerAcceptanceUuidv7Generator(), new DeferredWorkerClock());
        $orchestrator = new DeferredAcceptanceOrchestrator(
            $this->connection,
            $this->sender,
            $this->journal,
            new JournalRecordFactory($identifiers, new DeferredWorkerClock()),
        );

        $orchestrator->accept(
            new DeferredOperationMessage(
                $context->operationId(),
                $encoded->operationType(),
                $encoded->schemaVersion(),
                $encoded->encodedPayload(),
                $encoded->encodedContext(),
                $context->receivedAt(),
            ),
            $envelope,
            $metadata,
        );
    }

    private function runtime(
        OperationHandler $handler,
        ?SupervisionPolicy $policy = null,
        ?ClaimExecutionGuard $guard = null,
        ?OutcomeWriter $outcomes = null,
        ?OperationMetadata $metadata = null,
    ): DeferredWorkerRuntime {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRuntimeUuidv7Generator(), $clock);

        return new DeferredWorkerRuntime(
            new DeferredWorkerRuntimeServices(
                new OperationRegistry([$metadata ?? $this->metadata()]),
                $this->codec,
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver(new DeferredWorkerContainer($handler)),
                $policy ?? new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0),
            ),
            new DeferredWorkerRuntimeStorage(
                $this->connection,
                new JournalRecordFactory($identifiers, $clock),
                $this->journal,
                new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA),
                $clock,
                $outcomes ?? $this->outcomes,
            ),
            $guard ?? new \BlackOps\Internal\Execution\DirectClaimExecutionGuard(),
        );
    }

    private function recovery(
        ?OperationMetadata $metadata = null,
        ?OperationHandler $handler = null,
    ): DeferredLeaseExpiredRecovery {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRecoveryUuidv7Generator(), $clock);

        return new DeferredLeaseExpiredRecovery(
            new DeferredWorkerRuntimeServices(
                new OperationRegistry([$metadata ?? $this->metadata()]),
                $this->codec,
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver(new DeferredWorkerContainer($handler ?? new CompletingWorkerReportHandler())),
                new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0),
            ),
            new DeferredWorkerRuntimeStorage(
                $this->connection,
                new JournalRecordFactory($identifiers, $clock),
                $this->journal,
                new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA),
                $clock,
                $this->outcomes,
            ),
        );
    }

    private function startAttemptWithoutCompleting(
        OperationClaim $claim,
        ?OperationMetadata $metadata = null,
        ?Operation $definition = null,
    ): void {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRuntimeUuidv7Generator(), $clock);
        $metadata ??= $this->metadata();
        $state = new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA);
        $records = new JournalRecordFactory($identifiers, $clock);
        $context = $this->codec->decodeContext($claim->message()->schemaVersion(), $claim->message()->encodedContext());
        $reservation = $state->reserveAttemptStarted($claim, $clock->now());
        $envelope = new OperationEnvelope(
            $definition ?? new WorkerReportOperation(),
            $this->codec->decodeValue(
                $metadata,
                $claim->message()->schemaVersion(),
                $claim->message()->encodedPayload(),
            ),
            new ExecutionContextFactory($identifiers, $clock)->startAttempt($context, $reservation->attemptNumber),
            new Deferred(),
        );
        $attempt = $envelope->context()->attempt();

        self::assertNotNull($attempt);

        $state->recordCurrentAttempt($claim, $attempt, $clock->now());
        $this->journal->append($records->attemptStarted($envelope, $metadata, $reservation->sequence));
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            WorkerReportOperation::class,
            WorkerReportValue::class,
            WorkerReportHandler::class,
            WorkerReportDone::class,
            Deferred::class,
        );
    }

    private function selfHandledMetadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            RequiredSelfHandledWorkerOperation::class,
            WorkerReportValue::class,
            RequiredSelfHandledWorkerOperation::class,
            WorkerReportDone::class,
            Deferred::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT
                *,
                current_attempt_id::text AS current_attempt_id,
                to_char(current_attempt_started_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS current_attempt_started_at
            FROM ' . self::SCHEMA . '.operations');

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function deadLetterRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT
                operation_id::text AS operation_id,
                final_attempt_id::text AS final_attempt_id,
                final_attempt_number,
                reason_type,
                reason_message,
                to_char(moved_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS moved_at
            FROM ' . self::SCHEMA . '.dead_letters');

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<JournalRecord>
     */
    private function records(): array
    {
        return array_values(iterator_to_array($this->journal->records(OperationId::fromString(self::OPERATION_ID))));
    }

    private function connection(): Connection
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (int) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $db,
            'user' => $user,
            'password' => $password,
        ]);
    }
}

final readonly class WorkerReportOperation implements Operation {}

final readonly class WorkerReportValue implements OperationValue
{
    public function __construct(
        public string $reportName,
    ) {}
}

final readonly class WorkerReportDone implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class WorkerOperationDependency
{
    public function __construct(
        public string $value,
    ) {}
}

final class RequiredSelfHandledWorkerOperation implements Operation, OperationHandler
{
    public ?string $handledWith = null;

    public function __construct(
        private WorkerOperationDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $value = $operation->value();
        if (!$value instanceof WorkerReportValue) {
            throw new \LogicException('Worker report handler requires WorkerReportValue.');
        }

        $this->handledWith = $this->dependency->value;

        return OperationResult::completed(new WorkerReportDone('done-' . $value->reportName));
    }
}

/** @implements OperationHandler<WorkerReportValue, WorkerReportDone> */
abstract class WorkerReportHandler implements OperationHandler {}

final class CompletingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        $value = $operation->value();

        if (!$value instanceof WorkerReportValue) {
            throw new \LogicException('Worker report handler requires WorkerReportValue.');
        }

        return OperationResult::completed(new WorkerReportDone('done-' . $value->reportName));
    }
}

final class RejectingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::businessRule('report_rejected'));
    }
}

final class ThrowingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new RuntimeException('boom');
    }
}

final class RetryableThrowingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new RetryableWorkerRuntimeException('temporary boom');
    }
}

final class ClaimLostWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new WorkerClaimLostException('claim lost');
    }
}

final class ObservingWorkerClaimExecutionGuard implements ClaimExecutionGuard
{
    /** @var list<string> */
    public array $states = [];

    /** @var list<int> */
    public array $journalCounts = [];

    public function __construct(
        private Connection $connection,
        private string $schema,
    ) {}

    public function run(OperationClaim $claim, Closure $operation): mixed
    {
        $this->observe();
        $result = $operation();
        $this->observe();

        return $result;
    }

    private function observe(): void
    {
        $this->states[] = (string) $this->connection->fetchOne('SELECT state FROM ' . $this->schema . '.operations');
        $this->journalCounts[] = (int) $this->connection->fetchOne(
            'SELECT count(*) FROM ' . $this->schema . '.journal',
        );
    }
}

final readonly class FailingWorkerOutcomeWriter implements OutcomeWriter
{
    public function save(OutcomeRecord $record): void
    {
        throw new OutcomeStoreException('outcome unavailable');
    }
}

final class RetryableWorkerRuntimeException extends RuntimeException implements RetryableException {}

final readonly class AlwaysDeadLetterSupervisionPolicy implements SupervisionPolicy
{
    public function decide(\Throwable $error, AttemptContext $attempt): SupervisionDecision
    {
        return SupervisionDecision::deadLetter();
    }
}

final readonly class DeferredWorkerContainer implements ContainerInterface
{
    public function __construct(
        private OperationHandler $handler,
    ) {}

    public function get(string $id): mixed
    {
        return $this->handler;
    }

    public function has(string $id): bool
    {
        return $id === WorkerReportHandler::class;
    }
}

final readonly class DeferredWorkerClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:02:00.000000Z');
    }
}

final class DeferredWorkerAcceptanceUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687733',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687734',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}

final class DeferredWorkerRuntimeUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687735',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687736',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687737',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687738',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687739',
        '019f32ab-2be0-7b38-a0a7-1ab2f968773a',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}

final class DeferredWorkerRecoveryUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687740',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687741',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687742',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
