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
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\Exception\LifecycleTransitionException;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class InlineDispatcherTest extends TestCase
{
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

    public function testHandlerExceptionPropagates(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->dispatcher(new ThrowingDispatchHandler())->dispatch(new DispatchOperation(), new DispatchValue('hello'));
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
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(0, $handler->calls);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted],
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
        $this->expectException(JournalWriteFailed::class);

        $this->dispatcher(new DispatchHandler(), new FailingJournalWriter())->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
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
        } catch (JournalWriteFailed) {
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
        } catch (LifecycleTransitionException) {
        }

        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::AttemptSucceeded],
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
        );
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

final readonly class DispatchOperation implements Operation {}

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

final class RecordingJournalObserver implements JournalObserver
{
    /** @var list<ObservedJournalRecord> */
    public array $records = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->records[] = $record;
    }
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
