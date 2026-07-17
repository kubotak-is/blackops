<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\AttemptContext;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Exception\OperationRejectedException;
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
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Core\Supervision\SupervisionDecision;
use BlackOps\Core\Supervision\SupervisionPolicy;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ClaimExecutionGuard;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredLeaseExpiredRecovery;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\SupervisedHandlerFailureException;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->connection->executeStatement(
            'CREATE TABLE ' . self::SCHEMA . '.business_updates (id INTEGER PRIMARY KEY, value TEXT NOT NULL)',
        );
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
        foreach (array_slice($records, 2) as $record) {
            self::assertNull($record->operation->actorContext?->origin());
            self::assertNull($record->operation->actorContext?->authorization());
            self::assertSame('worker-a', $record->operation->actorContext?->execution()->id());
            self::assertSame('system', $record->operation->actorContext?->execution()->type());
        }
        $storedOutcome = $this->outcomes->find(OperationId::fromString(self::OPERATION_ID));
        self::assertNotNull($storedOutcome);
        self::assertInstanceOf(WorkerReportDone::class, $storedOutcome->outcome());
        self::assertSame('done-weekly', $storedOutcome->outcome()->message);
    }

    public function testWorkerReconnectsGeneratedConnectionBeforeResolvingHandler(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $application = $this->createMock(Connection::class);
        $application
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willReturnOnConsecutiveCalls(self::throwException(new RuntimeException('stale')), 1);
        $application->expects(self::once())->method('close');
        $application->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, connections: $this->lifecycle(['app' => $application]))->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
    }

    public function testWorkerDoesNotResolveHandlerWhenConnectionReconnectFails(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $application = $this->createMock(Connection::class);
        $application
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willThrowException(new RuntimeException('database unavailable'));
        $application->expects(self::exactly(2))->method('close');
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler, connections: $this->lifecycle(['app' => $application]))->run($claim);
            self::fail('Expected connection health check failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('database unavailable', $exception->getMessage());
        }

        self::assertSame(0, $handler->calls);
        self::assertSame('running', $this->operationRow()['state']);
    }

    public function testWorkerClosesEveryGeneratedConnectionAfterSupervisedFailure(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        foreach ([$app, $analytics] as $connection) {
            $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
            $connection->expects(self::once())->method('close');
        }
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime(new ThrowingWorkerReportHandler(), connections: $this->lifecycle([
                'app' => $app,
                'analytics' => $analytics,
            ]))->run($claim);
            self::fail('Expected supervised handler failure.');
        } catch (SupervisedHandlerFailureException) {
        }

        self::assertSame('failed', $this->operationRow()['state']);
    }

    public function testWorkerReusesClosedConnectionObjectOnNextRetryAttempt(): void
    {
        $handler = new RecoveringWorkerReportHandler();
        $application = $this->createMock(Connection::class);
        $application->expects(self::exactly(2))->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $application->expects(self::once())->method('close');
        $application->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $runtime = $this->runtime($handler, connections: $this->lifecycle(['app' => $application]));
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        try {
            $runtime->run($claim);
            self::fail('Expected the first attempt to be retried.');
        } catch (SupervisedHandlerFailureException) {
        }

        $retry = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:03:00.000000Z'),
        ));
        self::assertNotNull($retry);
        $result = $runtime->run($retry);

        self::assertTrue($result->isCompleted());
        self::assertSame(2, $handler->calls);
        self::assertSame('completed', $this->operationRow()['state']);
    }

    public function testWorkerTransactionLeakClosesConnectionAndEscapesSuccessfulAttempt(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $application = $this->createMock(Connection::class);
        $application->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $application->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $application->expects(self::once())->method('close');
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler, connections: $this->lifecycle(['app' => $application]))->run($claim);
            self::fail('Expected transaction leak failure.');
        } catch (\LogicException $exception) {
            self::assertSame('Application invocation left a database transaction active.', $exception->getMessage());
        }

        self::assertSame(1, $handler->calls);
        self::assertSame('completed', $this->operationRow()['state']);
    }

    public function testTransactionalWorkerCommitsBusinessTerminalAndAfterCommitTogether(): void
    {
        $metadata = $this->transactionalMetadata();
        $callbackStates = [];
        $handler = new TransactionalWorkerReportHandler($this->connection, function (TransactionRuntime $runtime) use (
            &$callbackStates,
        ): void {
            $runtime->afterCommit(self::class, 'completed', function () use (&$callbackStates): void {
                $callbackStates[] = $this->operationRow()['state'];
            });
        });
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        $result = $this->runtime($handler, metadata: $metadata)->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(['completed'], $callbackStates);
        self::assertSame(['business'], $this->businessValues());
        self::assertSame('completed', $this->operationRow()['state']);
        self::assertNotNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testTransactionalWorkerRollsBackBusinessBeforeRejectionAndSupervision(): void
    {
        $metadata = $this->transactionalMetadata();
        $rejecting = new TransactionalRejectingWorkerReportHandler($this->connection);
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        $result = $this->runtime($rejecting, metadata: $metadata)->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame([], $this->businessValues());
        self::assertSame('rejected', $this->operationRow()['state']);

        $this->connection->close();
        $this->setUp();
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(new TransactionalThrowingWorkerReportHandler($this->connection), metadata: $metadata)->run(
                $claim,
            );
            self::fail('Expected supervised transaction failure.');
        } catch (SupervisedHandlerFailureException) {
        }

        self::assertSame([], $this->businessValues());
        self::assertSame('failed', $this->operationRow()['state']);
        self::assertSame(JournalEvent::AttemptFailed, $this->records()[3]->event);

        $this->connection->close();
        $this->setUp();
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(
                new TransactionalThrowingWorkerReportHandler($this->connection),
                new AlwaysDeadLetterSupervisionPolicy(),
                metadata: $metadata,
            )->run($claim);
            self::fail('Expected supervised transactional dead letter.');
        } catch (SupervisedHandlerFailureException) {
        }

        self::assertSame([], $this->businessValues());
        self::assertSame('dead_lettered', $this->operationRow()['state']);
        self::assertSame(JournalEvent::OperationDeadLettered, $this->records()[4]->event);
    }

    public function testTransactionalWorkerAuthorizesBeforeBeginAndRetriesOnlyAfterRollback(): void
    {
        $metadata = $this->authorizedTransactionalMetadata();
        $policy = new TransactionStateWorkerAuthorizationPolicy($this->connection);
        $handler = new CompletingWorkerReportHandler();
        $this->accept($metadata, actors: self::userActors(), authorizationPolicy: $policy);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        $result = $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy)->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame([true, false], $policy->transactionStates);
        self::assertSame(0, $handler->calls);

        $this->connection->close();
        $this->setUp();
        $retryMetadata = $this->transactionalMetadata();
        $this->accept($retryMetadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(
                new TransactionalRetryableWorkerReportHandler($this->connection),
                metadata: $retryMetadata,
            )->run($claim);
            self::fail('Expected supervised retryable transaction failure.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(RetryableWorkerRuntimeException::class, $exception->getPrevious());
        }

        self::assertSame([], $this->businessValues());
        self::assertSame('retry_scheduled', $this->operationRow()['state']);
        self::assertSame(
            [JournalEvent::AttemptFailed, JournalEvent::AttemptRetryScheduled],
            array_column(array_slice($this->records(), 3), 'event'),
        );
    }

    public function testTransactionalWorkerRollsBackBusinessWhenOutcomeOrFencingFails(): void
    {
        $metadata = $this->transactionalMetadata();
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(
                new TransactionalWorkerReportHandler($this->connection),
                outcomes: new FailingWorkerOutcomeWriter(),
                metadata: $metadata,
            )->run($claim);
            self::fail('Expected supervised outcome failure.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(OutcomeStoreException::class, $exception->getPrevious());
        }

        self::assertSame([], $this->businessValues());
        self::assertSame('failed', $this->operationRow()['state']);
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));

        $this->connection->close();
        $this->setUp();
        $this->accept($metadata);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(new TransactionalFencingWorkerReportHandler($this->connection), metadata: $metadata)->run(
                $claim,
            );
            self::fail('Expected supervised fencing failure.');
        } catch (SupervisedHandlerFailureException) {
        }

        self::assertSame([], $this->businessValues());
        self::assertSame('failed', $this->operationRow()['state']);
        self::assertNull($this->outcomes->find(OperationId::fromString(self::OPERATION_ID)));
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
        self::assertSame(self::OPERATION_ID, $handler->operationId);
        self::assertSame(1, $handler->attemptNumber);
    }

    public function testWorkerReauthorizesWithOriginalUserAndWorkerExecutionActorBeforeHandler(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $actors = self::userActors();
        $policy = new SequencedWorkerAuthorizationPolicy([
            AuthorizationDecision::allow(),
            AuthorizationDecision::allow(),
        ]);
        $this->accept($metadata, actors: $actors, authorizationPolicy: $policy);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy)->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertCount(2, $policy->requests);
        $workerRequest = $policy->requests[1];
        self::assertEquals($actors->origin(), $workerRequest->context()->actorContext()?->origin());
        self::assertEquals($actors->authorization(), $workerRequest->actor());
        self::assertEquals($actors->authorization(), $workerRequest->context()->actorContext()?->authorization());
        self::assertSame('worker-a', $workerRequest->context()->actorContext()?->execution()->id());
        self::assertSame('system', $workerRequest->context()->actorContext()?->execution()->type());
        $records = $this->records();
        self::assertSame('http-runtime', $records[0]->operation->actorContext?->execution()->id());
        self::assertSame('http-runtime', $records[1]->operation->actorContext?->execution()->id());
        foreach (array_slice($records, 2) as $record) {
            self::assertEquals($actors->origin(), $record->operation->actorContext?->origin());
            self::assertEquals($actors->authorization(), $record->operation->actorContext?->authorization());
            self::assertSame('worker-a', $record->operation->actorContext?->execution()->id());
        }
    }

    public function testWorkerDoesNotElevateMissingAuthorizationActorToWorkerActor(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $policy = new SequencedWorkerAuthorizationPolicy([AuthorizationDecision::allow()]);
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy)->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame(RejectionCategory::Unauthorized, $result->rejectionReason()->category());
        self::assertSame('authorization.authentication_required', $result->rejectionReason()->code());
        self::assertSame(0, $handler->calls);
        self::assertSame([], $policy->requests);
        self::assertSame('rejected', $this->operationRow()['state']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::OperationRejected,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $this->records()),
        );
        self::assertNull($this->records()[3]->operation->actorContext?->authorization());
        self::assertSame('worker-a', $this->records()[3]->operation->actorContext?->execution()->id());
    }

    #[DataProvider('authorizationDenials')]
    public function testWorkerAuthorizationDenialIsTerminalWithoutFailureSupervision(
        AuthorizationDecision $denial,
        RejectionCategory $category,
        string $code,
    ): void {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $policy = new SequencedWorkerAuthorizationPolicy([AuthorizationDecision::allow(), $denial]);
        $this->accept($metadata, actors: self::userActors(), authorizationPolicy: $policy);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy)->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame($category, $result->rejectionReason()->category());
        self::assertSame($code, $result->rejectionReason()->code());
        self::assertSame(0, $handler->calls);
        self::assertSame('rejected', $this->operationRow()['state']);
        $records = $this->records();
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::OperationRejected,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame('worker-a', $records[3]->operation->actorContext?->execution()->id());
        self::assertNotInstanceOf(AttemptFailedData::class, $records[3]->data);
    }

    /**
     * @return iterable<string, array{AuthorizationDecision, RejectionCategory, string}>
     */
    public static function authorizationDenials(): iterable
    {
        yield 'unauthorized' => [
            AuthorizationDecision::unauthorized('authorization.expired'),
            RejectionCategory::Unauthorized,
            'authorization.expired',
        ];
        yield 'forbidden' => [
            AuthorizationDecision::forbid('authorization.report_forbidden'),
            RejectionCategory::Forbidden,
            'authorization.report_forbidden',
        ];
    }

    public function testPolicyBackendFailureUsesAttemptFailureAndNotSecurityRejection(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $failure = new RuntimeException('policy backend unavailable');
        $policy = new SequencedWorkerAuthorizationPolicy([AuthorizationDecision::allow(), $failure]);
        $this->accept($metadata, actors: self::userActors(), authorizationPolicy: $policy);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        try {
            $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy)->run($claim);
            self::fail('Expected policy backend failure to be supervised.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertSame(0, $handler->calls);
        self::assertSame('failed', $this->operationRow()['state']);
        $records = $this->records();
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
        self::assertInstanceOf(AttemptFailedData::class, $records[3]->data);
        self::assertSame(RuntimeException::class, $records[3]->data->errorType);
        self::assertSame('policy backend unavailable', $records[3]->data->errorMessage);
        self::assertSame('worker-a', $records[4]->operation->actorContext?->execution()->id());
    }

    public function testRetryablePolicyFailureIsReevaluatedOnNextAttempt(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $policy = new SequencedWorkerAuthorizationPolicy([
            AuthorizationDecision::allow(),
            new RetryableWorkerRuntimeException('temporary policy backend failure'),
            AuthorizationDecision::allow(),
        ]);
        $actors = self::userActors();
        $this->accept($metadata, actors: $actors, authorizationPolicy: $policy);
        $firstClaim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($firstClaim);
        $runtime = $this->runtime($handler, metadata: $metadata, authorizationPolicy: $policy);

        try {
            $runtime->run($firstClaim);
            self::fail('Expected retryable policy failure.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertInstanceOf(RetryableWorkerRuntimeException::class, $exception->getPrevious());
        }

        self::assertSame('retry_scheduled', $this->operationRow()['state']);
        $secondClaim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:02:01.000000Z'),
        ));
        self::assertNotNull($secondClaim);
        $result = $runtime->run($secondClaim);

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertCount(3, $policy->requests);
        self::assertSame(2, $policy->requests[2]->context()->attempt()?->number());
        self::assertEquals($actors->authorization(), $policy->requests[2]->actor());
        self::assertSame('worker-a', $policy->requests[2]->context()->actorContext()?->execution()->id());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::AttemptRetryScheduled,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $this->records()),
        );
        foreach (array_slice($this->records(), 2) as $record) {
            self::assertEquals($actors->origin(), $record->operation->actorContext?->origin());
            self::assertEquals($actors->authorization(), $record->operation->actorContext?->authorization());
            self::assertSame('worker-a', $record->operation->actorContext?->execution()->id());
        }
    }

    public function testPolicyBackendFailureCanDeadLetterWithWorkerActorContext(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $metadata = $this->authorizedMetadata();
        $policy = new SequencedWorkerAuthorizationPolicy([
            AuthorizationDecision::allow(),
            new RuntimeException('terminal policy backend failure'),
        ]);
        $actors = self::userActors();
        $this->accept($metadata, actors: $actors, authorizationPolicy: $policy);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));
        self::assertNotNull($claim);

        try {
            $this->runtime(
                $handler,
                new AlwaysDeadLetterSupervisionPolicy(),
                metadata: $metadata,
                authorizationPolicy: $policy,
            )->run($claim);
            self::fail('Expected policy backend failure to dead letter.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertSame('terminal policy backend failure', $exception->getPrevious()?->getMessage());
        }

        self::assertSame(0, $handler->calls);
        self::assertSame('dead_lettered', $this->operationRow()['state']);
        $records = $this->records();
        self::assertSame(JournalEvent::AttemptFailed, $records[3]->event);
        self::assertSame(JournalEvent::OperationDeadLettered, $records[4]->event);
        self::assertSame('worker-a', $records[4]->operation->actorContext?->execution()->id());
        self::assertEquals($actors->authorization(), $records[4]->operation->actorContext?->authorization());
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

    public function testWorkerNormalizesSelfHandledRejectedException(): void
    {
        $handler = new RejectingSelfHandledWorkerOperation();
        $metadata = new OperationMetadata(
            'report.generate',
            $handler::class,
            WorkerReportValue::class,
            $handler::class,
            WorkerReportDone::class,
            Deferred::class,
            true,
            true,
            'outcome',
        );
        $this->accept($metadata, $handler);
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);
        $result = $this->runtime($handler, metadata: $metadata)->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame('report.rejected', $result->rejectionReason()->code());
        self::assertSame('rejected', $this->operationRow()['state']);
        self::assertSame(JournalEvent::OperationRejected, $this->records()[3]->event);
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
        $actors = self::userActors();
        $this->accept(actors: $actors);
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
        foreach (array_slice($records, 2) as $record) {
            self::assertEquals($actors->origin(), $record->operation->actorContext?->origin());
            self::assertEquals($actors->authorization(), $record->operation->actorContext?->authorization());
            self::assertSame('worker-a', $record->operation->actorContext?->execution()->id());
            self::assertSame('system', $record->operation->actorContext?->execution()->type());
        }
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

    private function accept(
        ?OperationMetadata $metadata = null,
        ?Operation $definition = null,
        ?ActorContext $actors = null,
        ?AuthorizationPolicy $authorizationPolicy = null,
    ): void {
        $metadata ??= $this->metadata();
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: $actors,
        );
        $value = new WorkerReportValue('weekly');
        $encoded = $this->codec->encode($metadata, $value, $context);
        $envelope = new OperationEnvelope($definition ?? new WorkerReportOperation(), $value, $context, new Deferred());
        $identifiers = new IdentifierFactory(new DeferredWorkerAcceptanceUuidv7Generator(), new DeferredWorkerClock());
        $container = new DeferredWorkerContainer($definition ?? new WorkerReportOperation(), $authorizationPolicy);
        $orchestrator = new DeferredAcceptanceOrchestrator(
            $this->connection,
            $this->sender,
            $this->journal,
            new JournalRecordFactory($identifiers, new DeferredWorkerClock()),
            authorization: $authorizationPolicy === null
                ? null
                : new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
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
        object $handler,
        ?SupervisionPolicy $policy = null,
        ?ClaimExecutionGuard $guard = null,
        ?OutcomeWriter $outcomes = null,
        ?OperationMetadata $metadata = null,
        ?AuthorizationPolicy $authorizationPolicy = null,
        ?ApplicationDatabaseConnectionLifecycle $connections = null,
    ): DeferredWorkerRuntime {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRuntimeUuidv7Generator(), $clock);
        $container = new DeferredWorkerContainer($handler, $authorizationPolicy);
        $resolvedMetadata = $metadata ?? $this->metadata();
        $scope = new ExecutionScopeProvider();
        $manager = new DeferredWorkerDatabaseManager($this->connection);
        $transactionRuntime = new TransactionRuntime($manager, new IgnoringDeferredAfterCommitReporter(), $scope);
        if ($handler instanceof TransactionRuntimeAwareWorkerHandler) {
            $handler->setTransactionRuntime($transactionRuntime);
        }
        $operationTransactions = $resolvedMetadata->transactionConnection === null
            ? null
            : new OperationTransactionCoordinator($transactionRuntime, $manager, $this->connection);

        return new DeferredWorkerRuntime(
            new DeferredWorkerRuntimeServices(
                new OperationRegistry([$resolvedMetadata]),
                $this->codec,
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver($container),
                new ActorRef('worker-a', 'system'),
                new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
                $policy ?? new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0),
            ),
            new DeferredWorkerRuntimeStorage(
                $this->connection,
                new JournalRecordFactory($identifiers, $clock),
                $this->journal,
                new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA),
                $clock,
                $outcomes ?? $this->outcomes,
                scope: $scope,
                transactions: $operationTransactions,
            ),
            $guard ?? new \BlackOps\Internal\Execution\DirectClaimExecutionGuard(),
            connections: $connections,
        );
    }

    /** @param array<string, Connection> $connections */
    private function lifecycle(array $connections): ApplicationDatabaseConnectionLifecycle
    {
        $parameters = [];
        foreach (array_keys($connections) as $name) {
            $parameters[$name] = ['name' => $name];
        }
        $manager = new DoctrineDatabaseManager(
            array_key_first($connections),
            $parameters,
            static fn(array $parameters): Connection => $connections[$parameters['name']],
        );
        foreach (array_keys($connections) as $name) {
            $manager->connection($name);
        }

        return new ApplicationDatabaseConnectionLifecycle($manager);
    }

    private function recovery(
        ?OperationMetadata $metadata = null,
        ?object $handler = null,
    ): DeferredLeaseExpiredRecovery {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRecoveryUuidv7Generator(), $clock);
        $container = new DeferredWorkerContainer($handler ?? new CompletingWorkerReportHandler());

        return new DeferredLeaseExpiredRecovery(
            new DeferredWorkerRuntimeServices(
                new OperationRegistry([$metadata ?? $this->metadata()]),
                $this->codec,
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver($container),
                new ActorRef('worker-a', 'system'),
                new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
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
            new ExecutionContextFactory($identifiers, $clock)->startAttempt(
                $context,
                $reservation->attemptNumber,
                new ActorRef('worker-a', 'system'),
            ),
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

    private function transactionalMetadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            WorkerReportOperation::class,
            WorkerReportValue::class,
            WorkerReportHandler::class,
            WorkerReportDone::class,
            Deferred::class,
            transactionConnection: 'app',
        );
    }

    private function authorizedTransactionalMetadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            WorkerReportOperation::class,
            WorkerReportValue::class,
            WorkerReportHandler::class,
            WorkerReportDone::class,
            Deferred::class,
            authorizationPolicy: TransactionStateWorkerAuthorizationPolicy::class,
            transactionConnection: 'app',
        );
    }

    /** @return list<string> */
    private function businessValues(): array
    {
        /** @var list<string> $values */
        $values = $this->connection->fetchFirstColumn(
            'SELECT value FROM ' . self::SCHEMA . '.business_updates ORDER BY id',
        );

        return $values;
    }

    private function authorizedMetadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            WorkerReportOperation::class,
            WorkerReportValue::class,
            WorkerReportHandler::class,
            WorkerReportDone::class,
            Deferred::class,
            authorizationPolicy: SequencedWorkerAuthorizationPolicy::class,
        );
    }

    private static function userActors(): ActorContext
    {
        $user = new ActorRef('user-123', 'user');

        return new ActorContext($user, $user, new ActorRef('http-runtime', 'system'));
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
            true,
            true,
            'outcome',
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

final class RequiredSelfHandledWorkerOperation implements Operation
{
    public ?string $handledWith = null;
    public ?string $operationId = null;
    public ?int $attemptNumber = null;

    public function __construct(
        private WorkerOperationDependency $dependency,
    ) {}

    public function handle(WorkerReportValue $value, ExecutionContext $context): WorkerReportDone
    {
        $this->handledWith = $this->dependency->value;
        $this->operationId = $context->operationId()->toString();
        $this->attemptNumber = $context->attempt()?->number();

        return new WorkerReportDone('done-' . $value->reportName);
    }
}

final readonly class RejectingSelfHandledWorkerOperation implements Operation
{
    public function handle(WorkerReportValue $value, ExecutionContext $context): WorkerReportDone
    {
        throw OperationRejectedException::businessRule('report.rejected');
    }
}

/** @implements OperationHandler<WorkerReportValue, WorkerReportDone> */
abstract class WorkerReportHandler implements OperationHandler {}

final class CompletingWorkerReportHandler extends WorkerReportHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->calls++;
        $value = $operation->value();

        if (!$value instanceof WorkerReportValue) {
            throw new \LogicException('Worker report handler requires WorkerReportValue.');
        }

        return OperationResult::completed(new WorkerReportDone('done-' . $value->reportName));
    }
}

interface TransactionRuntimeAwareWorkerHandler
{
    public function setTransactionRuntime(TransactionRuntime $runtime): void;
}

final class TransactionalWorkerReportHandler extends WorkerReportHandler implements TransactionRuntimeAwareWorkerHandler
{
    private ?TransactionRuntime $runtime = null;

    /** @param null|Closure(TransactionRuntime): void $registerAfterCommit */
    public function __construct(
        private Connection $connection,
        private ?Closure $registerAfterCommit = null,
    ) {}

    public function setTransactionRuntime(TransactionRuntime $runtime): void
    {
        $this->runtime = $runtime;
    }

    public function handle(OperationEnvelope $operation): OperationResult
    {
        self::assertTransactionActive($this->connection);
        $this->connection->insert('blackops_p3_010.business_updates', ['id' => 1, 'value' => 'business']);

        if ($this->registerAfterCommit !== null) {
            ($this->registerAfterCommit)(
                $this->runtime ?? throw new RuntimeException('Transaction runtime was not injected.'),
            );
        }

        return OperationResult::completed(new WorkerReportDone('done-weekly'));
    }

    private static function assertTransactionActive(Connection $connection): void
    {
        if (!$connection->isTransactionActive()) {
            throw new RuntimeException('Operation transaction is not active.');
        }
    }
}

final class TransactionalRejectingWorkerReportHandler extends WorkerReportHandler
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->connection->insert('blackops_p3_010.business_updates', ['id' => 1, 'value' => 'rejected']);

        return OperationResult::rejected(RejectionReason::businessRule('report.rejected'));
    }
}

final class TransactionalThrowingWorkerReportHandler extends WorkerReportHandler
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->connection->insert('blackops_p3_010.business_updates', ['id' => 1, 'value' => 'throwable']);

        throw new RuntimeException('transactional handler failure');
    }
}

final class TransactionalRetryableWorkerReportHandler extends WorkerReportHandler
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->connection->insert('blackops_p3_010.business_updates', ['id' => 1, 'value' => 'retryable']);

        throw new RetryableWorkerRuntimeException('retryable transactional failure');
    }
}

final class TransactionalFencingWorkerReportHandler extends WorkerReportHandler
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->connection->insert('blackops_p3_010.business_updates', ['id' => 1, 'value' => 'fencing']);
        $this->connection->executeStatement('UPDATE blackops_p3_010.operations SET fencing_token = fencing_token + 1');

        return OperationResult::completed(new WorkerReportDone('done-weekly'));
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

final class RecoveringWorkerReportHandler extends WorkerReportHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;

        if ($this->calls === 1) {
            throw new RetryableWorkerRuntimeException('temporary connection-adjacent failure');
        }

        return OperationResult::completed(new WorkerReportDone('recovered'));
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

final readonly class DeferredWorkerDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        if ($name !== null && $name !== 'app') {
            throw new RuntimeException('Unknown test connection.');
        }

        return $this->connection;
    }
}

final readonly class IgnoringDeferredAfterCommitReporter implements AfterCommitFailureReporter
{
    public function report(AfterCommitFailure $failure): void {}
}

final class RetryableWorkerRuntimeException extends RuntimeException implements RetryableException {}

final class SequencedWorkerAuthorizationPolicy implements AuthorizationPolicy
{
    /** @var list<AuthorizationRequest> */
    public array $requests = [];

    /** @param list<AuthorizationDecision|\Throwable> $results */
    public function __construct(
        private array $results,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $this->requests[] = $request;
        $result = array_shift($this->results) ?? AuthorizationDecision::allow();

        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}

final class TransactionStateWorkerAuthorizationPolicy implements AuthorizationPolicy
{
    /** @var list<bool> */
    public array $transactionStates = [];

    private int $calls = 0;

    public function __construct(
        private Connection $connection,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $this->transactionStates[] = $this->connection->isTransactionActive();
        ++$this->calls;

        return $this->calls === 1
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::forbid('authorization.transaction_forbidden');
    }
}

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
        private object $handler,
        private ?AuthorizationPolicy $authorizationPolicy = null,
    ) {}

    public function get(string $id): mixed
    {
        if ($this->authorizationPolicy !== null && $id === $this->authorizationPolicy::class) {
            return $this->authorizationPolicy;
        }

        return $this->handler;
    }

    public function has(string $id): bool
    {
        return (
            $id === WorkerReportHandler::class
            || $id === $this->handler::class
            || $this->authorizationPolicy !== null
            && $id === $this->authorizationPolicy::class
        );
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
        '019f32ab-2be0-7b38-a0a7-1ab2f968773b',
        '019f32ab-2be0-7b38-a0a7-1ab2f968773c',
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
