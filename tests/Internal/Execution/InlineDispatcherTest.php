<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\InMemoryIdempotencyStore;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\Exception\LifecycleTransitionException;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

final class InlineDispatcherTest extends TestCase
{
    public function testKeyedCompletedReplayKeepsTypedResultOperationAndJournalIdentity(): void
    {
        $store = new InMemoryIdempotencyStore();
        $journal = new RecordingJournalWriter();
        $observer = new RecordingJournalObserver();
        $handler = new CountingDispatchHandler();
        $dispatcher = $this->dispatcher(
            $handler,
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('inline', $observer)]),
            ),
            idempotency: $store,
            retention: RetentionPeriod::days(3),
        );
        $actor = new ActorRef('user-123', 'user');
        $key = new IdempotencyKey('inline-replay-key');
        $context = new ActorContext($actor, $actor, $actor);

        $first = $dispatcher->dispatch(
            new DispatchOperation(),
            new DispatchValue('same', 'inline-secret'),
            $context,
            $key,
        );
        $journalCount = count($journal->records);
        $second = $dispatcher->dispatch(
            new DispatchOperation(),
            new DispatchValue('same', 'inline-secret'),
            $context,
            $key,
        );

        self::assertTrue($first->isCompleted());
        self::assertTrue($second->isCompleted());
        self::assertSame($first->operationId()?->toString(), $second->operationId()?->toString());
        self::assertTrue($second->isReplayed());
        self::assertSame(1, $handler->calls);
        self::assertCount($journalCount, $journal->records);
        self::assertStringNotContainsString('inline-replay-key', serialize($store));
        self::assertStringNotContainsString('inline-replay-key', serialize($journal->records));
        self::assertStringNotContainsString('inline-secret', serialize($store));
        self::assertStringNotContainsString('inline-secret', serialize($observer->records));
    }

    public function testKeyedRejectedReplayConflictProcessingAndExpiredMatrix(): void
    {
        $actor = new ActorRef('user-123', 'user');
        $context = new ActorContext($actor, $actor, $actor);
        $key = new IdempotencyKey('matrix-key');
        $store = new InMemoryIdempotencyStore();
        $handler = new RejectingDispatchHandler();
        $dispatcher = $this->dispatcher($handler, idempotency: $store, retention: RetentionPeriod::days(3));
        $first = $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        $replay = $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        $conflict = $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('different'), $context, $key);

        self::assertTrue($first->isRejected());
        self::assertSame($first->rejectionReason()->code(), $replay->rejectionReason()->code());
        self::assertSame($first->operationId()?->toString(), $replay->operationId()?->toString());
        self::assertTrue($replay->isReplayed());
        self::assertSame('idempotency_conflict', $conflict->rejectionReason()->code());
        self::assertNull($conflict->operationId());

        $processingStore = new InMemoryIdempotencyStore();
        $scope = new IdempotencyScopeHasher()->hash('dispatch.test', $actor, $key);
        $fingerprint = new OperationValueFingerprinter()->fingerprint('dispatch.test', new DispatchValue('processing'));
        $operationId = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $processingStore->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operationId,
            new Inline(),
            new DateTimeImmutable('2026-07-06T00:00:00Z'),
            new DateTimeImmutable('2026-07-09T00:00:00Z'),
        );
        $processing = $this->dispatcher(
            new CountingDispatchHandler(),
            idempotency: $processingStore,
            retention: RetentionPeriod::days(3),
        )->dispatch(new DispatchOperation(), new DispatchValue('processing'), $context, $key);
        self::assertSame('idempotency_in_progress', $processing->rejectionReason()->code());
        self::assertNull($processing->operationId());

        $expiredStore = new InMemoryIdempotencyStore();
        $expiredStore->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operationId,
            new Inline(),
            new DateTimeImmutable('2026-07-06T00:00:00Z'),
            new DateTimeImmutable('2026-07-09T00:00:00Z'),
        );
        $expiredStore->terminalize(
            $operationId,
            new TerminalRecord(
                $scope,
                $key->hash(),
                $fingerprint,
                $operationId,
                new Inline(),
                new DateTimeImmutable('2026-07-06T00:00:00Z'),
                new DateTimeImmutable('2026-07-09T00:00:00Z'),
            ),
        );
        $expired = $this->dispatcher(
            new CountingDispatchHandler(),
            idempotency: $expiredStore,
            retention: RetentionPeriod::days(3),
        )->dispatch(new DispatchOperation(), new DispatchValue('processing'), $context, $key);
        self::assertSame('idempotency_expired', $expired->rejectionReason()->code());
        self::assertNull($expired->operationId());
    }

    public function testKeyedAuthorizationIsEvaluatedOncePerCallAndDenialBypassesRecord(): void
    {
        $policy = new RecordingDispatchPolicy(AuthorizationDecision::allow());
        $store = new InMemoryIdempotencyStore();
        $handler = new CountingDispatchHandler();
        $dispatcher = $this->dispatcher(
            $handler,
            idempotency: $store,
            retention: RetentionPeriod::days(3),
            policy: $policy,
            metadata: $this->authorizedMetadata($handler),
        );
        $actor = new ActorRef('user-123', 'user');
        $context = new ActorContext($actor, $actor, $actor);
        $key = new IdempotencyKey('auth-key');
        $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        self::assertSame(2, $policy->calls);

        $deny = new RecordingDispatchPolicy(AuthorizationDecision::forbid('authorization.denied'));
        $deniedHandler = new CountingDispatchHandler();
        $denied = $this->dispatcher(
            $deniedHandler,
            idempotency: $store,
            retention: RetentionPeriod::days(3),
            policy: $deny,
            metadata: $this->authorizedMetadata($deniedHandler),
        )->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        self::assertSame('authorization.denied', $denied->rejectionReason()->code());
        self::assertSame(1, $deny->calls);
        self::assertSame(0, $deniedHandler->calls);
        self::assertNull($denied->operationId());
        $replayed = $dispatcher->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key);
        self::assertTrue($replayed->isReplayed());
        self::assertSame(
            $replayed->operationId()?->toString(),
            $dispatcher
                ->dispatch(new DispatchOperation(), new DispatchValue('same'), $context, $key)
                ->operationId()
                ?->toString(),
        );
    }

    public function testAnonymousKeyedDispatchIsRejectedWithoutStoreRecordOrOperationId(): void
    {
        $store = new InMemoryIdempotencyStore();
        $result = $this->dispatcher(
            new CountingDispatchHandler(),
            idempotency: $store,
            retention: RetentionPeriod::days(3),
        )->dispatch(new DispatchOperation(), new DispatchValue('secret'), null, new IdempotencyKey('anonymous-key'));

        self::assertSame('idempotency_requires_authenticated_actor', $result->rejectionReason()->code());
        self::assertNull($result->operationId());
        $records = new ReflectionClass($store)->getProperty('records');
        $records->setAccessible(true);
        self::assertSame([], $records->getValue($store));
    }

    public function testEphemeralResultReturnsToCallerButOnlyEmptyCanonicalDataReachesJournalAndObserver(): void
    {
        $journal = new RecordingJournalWriter();
        $observer = new RecordingJournalObserver();
        $handler = new EphemeralDispatchHandler('raw-secret-must-not-appear');
        $metadata = new OperationMetadata(
            'dispatch.ephemeral',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EphemeralDispatchOutcome::class,
            Inline::class,
        );

        $result = $this->dispatcher(
            $handler,
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('ephemeral', $observer)]),
            ),
            metadata: $metadata,
        )->dispatch(new DispatchOperation(), new DispatchValue('hello', 'input-secret-must-not-appear'));

        $outcome = $result->outcome();
        self::assertInstanceOf(EphemeralDispatchOutcome::class, $outcome);
        self::assertSame('raw-secret-must-not-appear', $outcome->token);
        self::assertInstanceOf(EmptyJournalData::class, $journal->records[0]->data);
        $completed = $journal->records[3]->data;
        self::assertInstanceOf(OperationCompletedData::class, $completed);
        self::assertInstanceOf(EmptyOutcome::class, $completed->outcome);
        self::assertCount(4, $observer->records);
        $surface = serialize([$journal->records, $observer->records]);
        self::assertStringNotContainsString('raw-secret-must-not-appear', $surface);
        self::assertStringNotContainsString('input-secret-must-not-appear', $surface);
    }

    public function testEphemeralProjectionFailureRollsBackBeforeCommitAndRecordsSafeFailure(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE business_updates (value TEXT NOT NULL)');
        [$coordinator, $scope] = $this->transactionCoordinator($connection);
        $handler = new TransactionalInlineDispatchHandler(
            $connection,
            OperationResult::completed(new EphemeralDispatchOutcome("\xB1\x31")),
        );
        $journal = new RecordingJournalWriter();
        $metadata = new OperationMetadata(
            'dispatch.ephemeral.invalid',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EphemeralDispatchOutcome::class,
            Inline::class,
            transactionConnection: 'app',
        );

        try {
            $this->dispatcher(
                $handler,
                $journal,
                scope: $scope,
                metadata: $metadata,
                transactions: $coordinator,
            )->dispatch(new DispatchOperation(), new DispatchValue('invalid'));
            self::fail('Expected ephemeral projection failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame(
                'Ephemeral operation outcome cannot be projected safely.',
                $failure->primaryFailure()->getMessage(),
            );
            self::assertStringNotContainsString("\xB1\x31", $failure->getMessage());
        }

        self::assertSame([], $connection->fetchFirstColumn('SELECT value FROM business_updates'));
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
        self::assertInstanceOf(EmptyJournalData::class, $journal->records[0]->data);
        self::assertStringNotContainsString("\xB1\x31", serialize($journal->records));
    }

    public function testUndeclaredEphemeralResultFailsBeforeActualOutcomeReachesCanonicalWriter(): void
    {
        $journal = new RecordingJournalWriter();
        $handler = new EphemeralDispatchHandler('undeclared-credential-must-not-reach-writer');
        $metadata = new OperationMetadata(
            'dispatch.ephemeral.undeclared',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
        );

        try {
            $this->dispatcher($handler, $journal, metadata: $metadata)->dispatch(
                new DispatchOperation(),
                new DispatchValue('ordinary-input'),
            );
            self::fail('Expected undeclared ephemeral outcome failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame(
                'Ephemeral operation outcome does not match its declared contract.',
                $failure->primaryFailure()->getMessage(),
            );
            self::assertStringNotContainsString('undeclared-credential-must-not-reach-writer', $failure->getMessage());
        }

        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
        self::assertStringNotContainsString(
            'undeclared-credential-must-not-reach-writer',
            serialize($journal->records),
        );
    }

    public function testDispatchBuildsAttemptEnvelopeAndReturnsHandlerResult(): void
    {
        $journal = new RecordingJournalWriter();
        $result = $this->dispatcher(new DispatchHandler(), $journal)->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
        );

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(EmptyOutcome::class, $result->outcome());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame([1, 2, 3, 4], array_column($journal->records, 'sequence'));
    }

    public function testProxySubclassDispatchUsesRegisteredParentMetadataThroughTerminalJournal(): void
    {
        $journal = new RecordingJournalWriter();
        $result = $this->dispatcher(new DispatchHandler(), $journal)->dispatch(
            new ProxiedDispatchOperation(),
            new DispatchValue('proxied'),
        );

        self::assertTrue($result->isCompleted());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame(
            ['dispatch.test'],
            array_values(array_unique(array_map(
                static fn(JournalRecord $record): string => $record->operation->type,
                $journal->records,
            ))),
        );
    }

    public function testTypedHandlerReceivesInlineContextWithoutAttempt(): void
    {
        $handler = new TypedContextDispatchOperation();
        $metadata = new OperationMetadata(
            'dispatch.typed',
            TypedContextDispatchOperation::class,
            DispatchValue::class,
            TypedContextDispatchOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
            true,
            'void',
        );

        $this->dispatcher($handler, metadata: $metadata)->dispatch($handler, new DispatchValue('typed'));

        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $handler->context?->operationId()->toString());
        self::assertNull($handler->context?->attempt());
    }

    public function testMismatchedValueIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->dispatcher(new DispatchHandler())->dispatch(new DispatchOperation(), new OtherDispatchValue());
    }

    public function testHandlerExceptionRecordsTerminalFailureAndPreservesPrimaryThrowable(): void
    {
        $journal = new RecordingJournalWriter();

        try {
            $this->dispatcher(new ThrowingDispatchHandler(), $journal)->dispatch(
                new DispatchOperation(),
                new DispatchValue('hello'),
            );
            self::fail('Expected operation execution failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertInstanceOf(\RuntimeException::class, $failure->primaryFailure());
            self::assertSame('handler failed', $failure->primaryFailure()->getMessage());
            self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $failure->operationId()->toString());
            self::assertNull($failure->recordingFailure());
        }

        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
        self::assertSame([1, 2, 3, 4], array_column($journal->records, 'sequence'));
        self::assertInstanceOf(AttemptFailedData::class, $journal->records[2]->data);
        self::assertFalse($journal->records[2]->data->retryable);
        self::assertInstanceOf(OperationFailedData::class, $journal->records[3]->data);
        self::assertFalse($journal->records[3]->data->retryable);
        self::assertSame($journal->records[1]->attempt?->id, $journal->records[2]->attempt?->id);
        self::assertSame($journal->records[1]->attempt?->id, $journal->records[3]->attempt?->id);
    }

    public function testRejectedResultWritesTerminalRejectedEvent(): void
    {
        $journal = new RecordingJournalWriter();
        $result = $this->dispatcher(new RejectingDispatchHandler(), $journal)->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
        );

        self::assertTrue($result->isRejected());
        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $result->operationId()?->toString());
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame([1, 2, 3], array_column($journal->records, 'sequence'));
    }

    public function testAnonymousAuthorizedOperationRejectsAfterAttemptWithoutCallingPolicyOrHandler(): void
    {
        $policy = new RecordingDispatchPolicy(AuthorizationDecision::allow());
        $handler = new CountingDispatchHandler();
        $journal = new RecordingJournalWriter();

        $result = $this->dispatcher(
            $handler,
            $journal,
            metadata: $this->authorizedMetadata($handler),
            policy: $policy,
        )->dispatch(new DispatchOperation(), new DispatchValue('anonymous'));

        self::assertTrue($result->isRejected());
        self::assertSame('authorization.authentication_required', $result->rejectionReason()->code());
        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $result->operationId()?->toString());
        self::assertSame(0, $policy->calls);
        self::assertSame(0, $handler->calls);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::OperationRejected],
            array_column($journal->records, 'event'),
        );
        self::assertSame([1, 2, 3], array_column($journal->records, 'sequence'));
    }

    public function testAuthenticatedAllowPassesAttemptContextAndRunsHandler(): void
    {
        $policy = new RecordingDispatchPolicy(AuthorizationDecision::allow());
        $handler = new CountingDispatchHandler();
        $actor = new ActorRef('user-123', 'user');

        $result = $this->dispatcher($handler, metadata: $this->authorizedMetadata($handler), policy: $policy)->dispatch(
            new DispatchOperation(),
            new DispatchValue('allow'),
            new ActorContext($actor, $actor, $actor),
        );

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $policy->calls);
        self::assertSame(1, $handler->calls);
        self::assertSame($actor, $policy->request?->actor());
        self::assertNotNull($policy->request?->context()->attempt());
    }

    public function testForbiddenDoesNotRunHandlerAndWritesRejected(): void
    {
        $policy = new RecordingDispatchPolicy(AuthorizationDecision::forbid('authorization.dispatch_forbidden'));
        $handler = new CountingDispatchHandler();
        $journal = new RecordingJournalWriter();
        $actor = new ActorRef('user-123', 'user');

        $result = $this->dispatcher(
            $handler,
            $journal,
            metadata: $this->authorizedMetadata($handler),
            policy: $policy,
        )->dispatch(new DispatchOperation(), new DispatchValue('forbid'), new ActorContext($actor, $actor, $actor));

        self::assertSame('authorization.dispatch_forbidden', $result->rejectionReason()->code());
        self::assertSame(0, $handler->calls);
        self::assertSame(JournalEvent::OperationRejected, $journal->records[2]->event);
    }

    public function testPolicyBackendExceptionPropagatesWithoutRejectedJournalOrHandler(): void
    {
        $failure = new \RuntimeException('policy backend unavailable');
        $policy = new RecordingDispatchPolicy(AuthorizationDecision::allow(), $failure);
        $handler = new CountingDispatchHandler();
        $journal = new RecordingJournalWriter();
        $actor = new ActorRef('user-123', 'user');

        try {
            $this->dispatcher(
                $handler,
                $journal,
                metadata: $this->authorizedMetadata($handler),
                policy: $policy,
            )->dispatch(
                new DispatchOperation(),
                new DispatchValue('failure'),
                new ActorContext($actor, $actor, $actor),
            );
            self::fail('Expected policy backend failure.');
        } catch (OperationExecutionFailed $exception) {
            self::assertSame($failure, $exception->primaryFailure());
            self::assertNull($exception->recordingFailure());
        }

        self::assertSame(0, $handler->calls);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
    }

    public function testRejectedExceptionWritesTerminalRejectedEvent(): void
    {
        $handler = new RejectingTypedDispatchOperation();
        $journal = new RecordingJournalWriter();
        $metadata = new OperationMetadata(
            'dispatch.typed.rejected',
            $handler::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            true,
            false,
            'void',
        );

        $result = $this->dispatcher($handler, $journal, metadata: $metadata)->dispatch(
            $handler,
            new DispatchValue('rejected'),
        );

        self::assertTrue($result->isRejected());
        self::assertSame('dispatch.rejected', $result->rejectionReason()->code());
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
    }

    public function testJournalWriterFailurePropagates(): void
    {
        try {
            $this->dispatcher(new DispatchHandler(), new FailingJournalWriter())->dispatch(
                new DispatchOperation(),
                new DispatchValue('hello'),
            );
            self::fail('Expected operation execution failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertInstanceOf(JournalWriteFailed::class, $failure->primaryFailure());
            self::assertNull($failure->recordingFailure());
        }
    }

    public function testFailureJournalWriteDoesNotReplacePrimaryHandlerThrowable(): void
    {
        $journal = new FailingTerminalJournalWriter();

        try {
            $this->dispatcher(new ThrowingDispatchHandler(), $journal)->dispatch(
                new DispatchOperation(),
                new DispatchValue('hello'),
            );
            self::fail('Expected operation execution failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame('handler failed', $failure->primaryFailure()->getMessage());
            self::assertInstanceOf(JournalWriteFailed::class, $failure->recordingFailure());
            self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $failure->operationId()->toString());
        }

        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted],
            array_column($journal->records, 'event'),
        );
    }

    public function testObservedRecordsAreDeliveredAfterCanonicalAppend(): void
    {
        $observer = new RecordingJournalObserver();
        $journal = new RecordingJournalWriter();

        $this->dispatcher(
            new DispatchHandler(),
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('observer', $observer)]),
            ),
        )->dispatch(new DispatchOperation(), new DispatchValue('hello', 'secret'));

        self::assertCount(4, $journal->records);
        self::assertCount(4, $observer->records);
        self::assertSame('hello', $observer->records[0]->data['value']['message']);
        self::assertArrayNotHasKey('password', $observer->records[0]->data['value']);
        self::assertStringStartsWith('hmac-sha256:', $observer->records[0]->data['value']['customerId']);
    }

    public function testTransactionalInlineCommitsBusinessAndTerminalBeforeObservation(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE business_updates (value TEXT NOT NULL)');
        [$coordinator, $scope] = $this->transactionCoordinator($connection);
        $observer = new TransactionStateJournalObserver($connection);
        $journal = new RecordingJournalWriter();
        $handler = new TransactionalInlineDispatchHandler($connection, OperationResult::completed());
        $metadata = new OperationMetadata(
            'dispatch.transactional',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            transactionConnection: 'app',
        );

        $result = $this->dispatcher(
            $handler,
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('transaction-state', $observer)]),
            ),
            scope: $scope,
            metadata: $metadata,
            transactions: $coordinator,
        )->dispatch(new DispatchOperation(), new DispatchValue('transactional'));

        self::assertTrue($result->isCompleted());
        self::assertSame(['business'], $connection->fetchFirstColumn('SELECT value FROM business_updates'));
        self::assertSame([false, false, false, false], $observer->transactionStates);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_column($journal->records, 'event'),
        );
    }

    public function testTransactionalInlineRollsBackBeforeRejectionAndStartsAfterAuthorization(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE business_updates (value TEXT NOT NULL)');
        [$coordinator, $scope] = $this->transactionCoordinator($connection);
        $handler = new TransactionalInlineDispatchHandler(
            $connection,
            OperationResult::rejected(RejectionReason::businessRule('dispatch.rejected')),
        );
        $metadata = new OperationMetadata(
            'dispatch.transactional.rejected',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            transactionConnection: 'app',
        );

        $result = $this->dispatcher($handler, scope: $scope, metadata: $metadata, transactions: $coordinator)->dispatch(
            new DispatchOperation(),
            new DispatchValue('rejected'),
        );

        self::assertTrue($result->isRejected());
        self::assertSame([], $connection->fetchFirstColumn('SELECT value FROM business_updates'));

        $policy = new TransactionStateDispatchPolicy($connection);
        $authorizedMetadata = new OperationMetadata(
            'dispatch.transactional.authorized',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            authorizationPolicy: $policy::class,
            transactionConnection: 'app',
        );
        $actor = new ActorRef('user-123', 'user');
        $denied = $this->dispatcher(
            $handler,
            scope: $scope,
            metadata: $authorizedMetadata,
            policy: $policy,
            transactions: $coordinator,
        )->dispatch(new DispatchOperation(), new DispatchValue('forbidden'), new ActorContext($actor, $actor, $actor));

        self::assertTrue($denied->isRejected());
        self::assertSame([false], $policy->transactionStates);
        self::assertSame(1, $handler->calls);
    }

    public function testTransactionalInlineRollsBackBeforeRecordingTerminalFailure(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE business_updates (value TEXT NOT NULL)');
        [$coordinator, $scope] = $this->transactionCoordinator($connection);
        $failure = new \RuntimeException('transactional handler credential detail');
        $handler = new FailingTransactionalInlineDispatchHandler($connection, $failure);
        $journal = new RecordingJournalWriter();
        $metadata = new OperationMetadata(
            'dispatch.transactional.failed',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            transactionConnection: 'app',
        );

        try {
            $this->dispatcher(
                $handler,
                $journal,
                scope: $scope,
                metadata: $metadata,
                transactions: $coordinator,
            )->dispatch(new DispatchOperation(), new DispatchValue('failed'));
            self::fail('Expected transactional operation failure.');
        } catch (OperationExecutionFailed $executionFailure) {
            self::assertSame($failure, $executionFailure->primaryFailure());
        }

        self::assertSame([], $connection->fetchFirstColumn('SELECT value FROM business_updates'));
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
    }

    public function testRollbackFailureDoesNotReplacePrimaryHandlerThrowable(): void
    {
        $active = false;
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$active): bool {
                return $active;
            });
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$active): void {
                $active = true;
            });
        $connection->method('rollBack')->willThrowException(new \RuntimeException('rollback credential detail'));
        [$coordinator, $scope] = $this->transactionCoordinator($connection);
        $handler = new ThrowingDispatchHandler();
        $journal = new RecordingJournalWriter();
        $metadata = new OperationMetadata(
            'dispatch.transactional.rollbackfailed',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            transactionConnection: 'app',
        );

        try {
            $this->dispatcher(
                $handler,
                $journal,
                scope: $scope,
                metadata: $metadata,
                transactions: $coordinator,
            )->dispatch(new DispatchOperation(), new DispatchValue('failed'));
            self::fail('Expected transactional operation failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame('handler failed', $failure->primaryFailure()->getMessage());
            self::assertNull($failure->recordingFailure());
        }

        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
    }

    public function testBestEffortObserverFailureDoesNotBlockDispatch(): void
    {
        $result = $this->dispatcher(
            new DispatchHandler(),
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('observer', new FailingJournalObserver())]),
            ),
        )->dispatch(new DispatchOperation(), new DispatchValue('hello'));

        self::assertTrue($result->isCompleted());
    }

    public function testHandlerRunsInsideExecutionScope(): void
    {
        $scope = new ExecutionScopeProvider();
        $handler = new ScopedDispatchHandler($scope);

        $this->dispatcher($handler, scope: $scope)->dispatch(new DispatchOperation(), new DispatchValue('hello'));

        self::assertTrue($handler->sawCurrentEnvelope);
        self::assertNull($scope->current());
    }

    public function testCanonicalAppendFailurePreventsObserverDelivery(): void
    {
        $observer = new RecordingJournalObserver();

        try {
            $this->dispatcher(
                new DispatchHandler(),
                new FailingJournalWriter(),
                observations: new JournalObservationPipeline(
                    new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                    new JournalObserverAggregator([new JournalObserverBinding('observer', $observer)]),
                ),
            )->dispatch(new DispatchOperation(), new DispatchValue('hello'));
            self::fail('Expected journal write failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertInstanceOf(JournalWriteFailed::class, $failure->primaryFailure());
        }

        self::assertSame([], $observer->records);
    }

    public function testObserverlessDispatcherDoesNotProjectJournalData(): void
    {
        $result = $this->dispatcher(new DispatchHandler())->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello', 'secret', 'customer-1'),
        );

        self::assertTrue($result->isCompleted());
    }

    public function testInvalidLifecycleTransitionPreventsTerminalRecordAppend(): void
    {
        $journal = new RecordingJournalWriter();

        try {
            $this->dispatcher(new DispatchHandler(), $journal, new InvalidFinalizingStateMachine())->dispatch(
                new DispatchOperation(),
                new DispatchValue('hello'),
            );
            self::fail('Expected lifecycle transition failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertInstanceOf(LifecycleTransitionException::class, $failure->primaryFailure());
        }

        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationFailed,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
    }

    private function dispatcher(
        object $handler,
        ?CanonicalJournalWriter $journal = null,
        ?LifecycleStateMachine $lifecycle = null,
        ?JournalObservationPipeline $observations = null,
        ?ExecutionScopeProvider $scope = null,
        ?OperationMetadata $metadata = null,
        ?AuthorizationPolicy $policy = null,
        ?OperationTransactionCoordinator $transactions = null,
        ?IdempotencyStore $idempotency = null,
        ?RetentionPeriod $retention = null,
    ): InlineDispatcher {
        $metadata ??= new OperationMetadata(
            'dispatch.test',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
        );
        $container = new class($handler) implements ContainerInterface {
            public function __construct(
                private readonly object $service,
            ) {}

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-06T00:00:00.000000Z');
            }
        };
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
            }
        };
        $identifiers = new IdentifierFactory($generator, $clock);
        $authorization = $policy === null
            ? null
            : new AuthorizationEvaluator(new AuthorizationPolicyResolver(new DispatchPolicyContainer($policy)));

        return new InlineDispatcher(
            new OperationRegistry([$metadata]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $journal ?? new RecordingJournalWriter(),
            $lifecycle ?? new LifecycleStateMachine(),
            $observations,
            $scope ?? new ExecutionScopeProvider(),
            authorization: $authorization,
            transactions: $transactions,
            idempotency: $idempotency,
            idempotencyRetention: $retention,
        );
    }

    /** @return array{OperationTransactionCoordinator, ExecutionScopeProvider} */
    private function transactionCoordinator(Connection $connection): array
    {
        $manager = new InlineDispatchDatabaseManager($connection);
        $scope = new ExecutionScopeProvider();
        $runtime = new TransactionRuntime($manager, new IgnoringInlineAfterCommitReporter(), $scope);

        return [new OperationTransactionCoordinator($runtime, $manager, $connection), $scope];
    }

    private function authorizedMetadata(object $handler): OperationMetadata
    {
        return new OperationMetadata(
            'dispatch.authorized',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
            authorizationPolicy: RecordingDispatchPolicy::class,
        );
    }
}

readonly class DispatchOperation implements Operation {}

final readonly class ProxiedDispatchOperation extends DispatchOperation {}

final class TypedContextDispatchOperation implements Operation
{
    public ?ExecutionContext $context = null;

    public function handle(DispatchValue $value, ExecutionContext $context): void
    {
        $this->context = $context;
    }
}

final readonly class RejectingTypedDispatchOperation implements Operation
{
    public function handle(DispatchValue $value): void
    {
        throw OperationRejectedException::conflict('dispatch.rejected');
    }
}

final readonly class DispatchValue implements OperationValue
{
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $password = 'secret',
        #[Sensitive(SensitiveMode::Hash)]
        public string $customerId = '',
    ) {}
}

final readonly class OtherDispatchValue implements OperationValue {}

final readonly class EphemeralDispatchOutcome implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
    ) {}
}

final readonly class EphemeralDispatchHandler implements OperationHandler
{
    public function __construct(
        private string $token,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new EphemeralDispatchOutcome($this->token));
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class DispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        if ($operation->context()->attempt() === null) {
            throw new LogicException('Attempt is required.');
        }
        return OperationResult::completed();
    }
}

final class CountingDispatchHandler implements OperationHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;

        return OperationResult::completed();
    }
}

final class TransactionalInlineDispatchHandler implements OperationHandler
{
    public int $calls = 0;

    public function __construct(
        private Connection $connection,
        private OperationResult $result,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;
        $this->connection->insert('business_updates', ['value' => 'business']);

        return $this->result;
    }
}

final readonly class FailingTransactionalInlineDispatchHandler implements OperationHandler
{
    public function __construct(
        private Connection $connection,
        private \Throwable $failure,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->connection->insert('business_updates', ['value' => 'business']);

        throw $this->failure;
    }
}

final class TransactionStateDispatchPolicy implements AuthorizationPolicy
{
    /** @var list<bool> */
    public array $transactionStates = [];

    public function __construct(
        private Connection $connection,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $this->transactionStates[] = $this->connection->isTransactionActive();

        return AuthorizationDecision::forbid('authorization.transaction_forbidden');
    }
}

final class RecordingDispatchPolicy implements AuthorizationPolicy
{
    public int $calls = 0;
    public ?AuthorizationRequest $request = null;

    public function __construct(
        private readonly AuthorizationDecision $decision,
        private readonly ?\Throwable $failure = null,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        ++$this->calls;
        $this->request = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

final readonly class DispatchPolicyContainer implements ContainerInterface
{
    public function __construct(
        private AuthorizationPolicy $policy,
    ) {}

    public function get(string $id): mixed
    {
        return $this->policy;
    }

    public function has(string $id): bool
    {
        return true;
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class ThrowingDispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new \RuntimeException('handler failed');
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class RejectingDispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::conflict('dispatch_rejected'));
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final class ScopedDispatchHandler implements OperationHandler
{
    public bool $sawCurrentEnvelope = false;

    public function __construct(
        private readonly ExecutionScopeProvider $scope,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $current = $this->scope->current();

        if ($current === null) {
            throw new LogicException('Execution scope is required.');
        }

        $this->sawCurrentEnvelope = $current === $operation && $current->context()->attempt() !== null;

        return OperationResult::completed();
    }
}

final class RecordingJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final readonly class FailingJournalWriter implements CanonicalJournalWriter
{
    public function append(JournalRecord $record): void
    {
        throw new JournalWriteFailed('journal unavailable');
    }
}

final class FailingTerminalJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        if (count($this->records) >= 2) {
            throw new JournalWriteFailed('terminal journal unavailable');
        }

        $this->records[] = $record;
    }
}

final class RecordingJournalObserver implements JournalObserver
{
    /** @var list<ObservedJournalRecord> */
    public array $records = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final class TransactionStateJournalObserver implements JournalObserver
{
    /** @var list<bool> */
    public array $transactionStates = [];

    public function __construct(
        private Connection $connection,
    ) {}

    public function observe(ObservedJournalRecord $record): void
    {
        $this->transactionStates[] = $this->connection->isTransactionActive();
    }
}

final readonly class InlineDispatchDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        if ($name !== null && $name !== 'app') {
            throw new LogicException('Unknown test connection.');
        }

        return $this->connection;
    }
}

final readonly class IgnoringInlineAfterCommitReporter implements AfterCommitFailureReporter
{
    public function report(AfterCommitFailure $failure): void {}
}

final readonly class FailingJournalObserver implements JournalObserver
{
    public function observe(ObservedJournalRecord $record): void
    {
        throw new JournalObservationFailed('observer unavailable');
    }
}

final readonly class InvalidFinalizingStateMachine extends LifecycleStateMachine
{
    public function next(?LifecycleState $current, JournalEvent $event): LifecycleState
    {
        if ($event === JournalEvent::OperationCompleted) {
            throw LifecycleTransitionException::invalid($current, $event);
        }

        return parent::next($current, $event);
    }
}
