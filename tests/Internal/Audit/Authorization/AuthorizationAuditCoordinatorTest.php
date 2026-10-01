<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authorization;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Database\DatabaseManager;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditAttempt;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditCoordinator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluation;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEventFactory;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyRegistry;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;

final class AuthorizationAuditCoordinatorTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string CORRELATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public function testRealEvaluatorRunsOnceAndCoordinatorPreparesARealAttempt(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, $clock, $identifiers] = $this->coordinator($policy, AuditDelivery::Durable);

        $attempt = $coordinator->evaluate(
            $this->metadata(CoordinatorTestPolicy::class),
            $this->envelope(new ActorRef('principal-1', 'user')),
        );

        self::assertTrue($attempt->evaluation()->isAllowed());
        self::assertSame(1, $policy->calls);
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $identifiers->calls);
    }

    public function testAttemptPreparationRequiresExactPolicyProjectionAgreement(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection, $factory, $selector, $delivery] = $this->coordinator(
            $policy,
            AuditDelivery::Durable,
        );
        $envelope = $this->envelope(new ActorRef('principal-1', 'user'));
        $attempt = $coordinator->evaluate($this->metadata(CoordinatorTestPolicy::class), $envelope);
        $evaluation = $attempt->evaluation();
        $request = $factory->create($evaluation, $envelope->context());

        self::assertNotNull($request);
        $plan = $selector->forDraft($request->draft());
        $draft = $request->draft();
        $basePolicy = $draft->policy;
        $valid = AuthorizationAuditAttempt::prepare($evaluation, $request, $plan, $delivery, $connection);
        self::assertInstanceOf(AuthorizationAuditAttempt::class, $valid);

        foreach ([
            new AuditRecordPolicy(
                'different.authorization',
                $basePolicy->version,
                $basePolicy->decision,
                $basePolicy->reasonCode,
                $basePolicy->auditDelivery,
            ),
            new AuditRecordPolicy(
                $basePolicy->id,
                $basePolicy->version + 1,
                $basePolicy->decision,
                $basePolicy->reasonCode,
                $basePolicy->auditDelivery,
            ),
            new AuditRecordPolicy(
                $basePolicy->id,
                $basePolicy->version,
                AuditPolicyDecision::Deny,
                'authorization_denied',
                $basePolicy->auditDelivery,
            ),
            new AuditRecordPolicy(
                $basePolicy->id,
                $basePolicy->version,
                $basePolicy->decision,
                'different_reason',
                $basePolicy->auditDelivery,
            ),
            new AuditRecordPolicy(
                $basePolicy->id,
                $basePolicy->version,
                $basePolicy->decision,
                $basePolicy->reasonCode,
                AuditDelivery::Required,
            ),
        ] as $changedPolicy) {
            $changedDraft = $this->draftWithPolicy($draft, $changedPolicy);
            $changedRequest = new AuditDeliveryRequest($changedDraft, $request->idempotencyKey());
            try {
                AuthorizationAuditAttempt::prepare($evaluation, $changedRequest, $plan, $delivery, $connection);
                self::fail('A mismatched evaluated policy projection must be rejected.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testTypeCorrectMismatchedSnapshotFailsAtReadAndDeliveryBoundaries(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection, $factory, $selector, $delivery] = $this->coordinator(
            $policy,
            AuditDelivery::Durable,
        );
        $envelope = $this->envelope(new ActorRef('principal-1', 'user'));
        $attempt = $coordinator->evaluate($this->metadata(CoordinatorTestPolicy::class), $envelope);
        $evaluation = $attempt->evaluation();
        $request = $factory->create($evaluation, $envelope->context());

        self::assertNotNull($request);
        $plan = $selector->forDraft($request->draft());
        $alternate = AuthorizationAuditEvaluation::fromDecision(
            new AuthorizationAuditPolicyBinding(
                $this->metadata(CoordinatorTestPolicy::class),
                'different.authorization',
                8,
                AuditDelivery::Durable,
            ),
            null,
        );
        $snapshot = [
            'evaluation' => $alternate,
            'request' => $request,
            'plan' => $plan,
            'delivery' => $delivery,
            'connection' => $connection,
        ];
        $candidate = new ReflectionClass(AuthorizationAuditAttempt::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuthorizationAuditAttempt::class, 'snapshot')->setValue(
            $candidate,
            new \SensitiveParameterValue($snapshot),
        );

        try {
            $candidate->evaluation();
            self::fail('A type-correct mismatched evaluation must be rejected when read.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        try {
            $candidate->deliverStandalone();
            self::fail('A type-correct mismatched evaluation must be rejected before delivery.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testNoPolicyAttemptHasNoAuditWorkAndCanBeDeliveredRepeatedly(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, $clock, $identifiers] = $this->coordinator($policy, AuditDelivery::Durable);

        $attempt = $coordinator->evaluate($this->metadata(null), $this->envelope(null));
        $attempt->assertMutation($this->metadata('ignored'), null);
        $attempt->deliverStandalone();
        $attempt->deliverStandalone();

        self::assertFalse($attempt->evaluation()->hasPolicy());
        self::assertSame(0, $policy->calls);
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $identifiers->calls);
    }

    public function testMutationBoundaryRequiresTheAttributedFrameworkConnectionAndRootState(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection] = $this->coordinator($policy, AuditDelivery::Required, mockConnection: true);
        $attempt = $coordinator->evaluate(
            $this->metadata(CoordinatorTestPolicy::class, 'primary'),
            $this->envelope(new ActorRef('principal-1', 'user')),
        );

        $databases = $this->createMock(DatabaseManager::class);
        $databases->expects(self::once())->method('connection')->with('primary')->willReturn($connection);
        $transactions = new OperationTransactionCoordinator(
            new ReflectionClass(TransactionRuntime::class)->newInstanceWithoutConstructor(),
            $databases,
            $connection,
        );
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);

        $attempt->assertMutation($this->metadata(CoordinatorTestPolicy::class, 'primary'), $transactions);

        $wrong = $this->createStub(Connection::class);
        try {
            $attempt->deliverInOwnedTransaction($wrong);
            self::fail('A different owned connection must be rejected.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testMutationBoundaryRejectsUnsupportedCompositionAndRootStates(): void
    {
        $nullTransactionsPolicy = new CoordinatorTestPolicy();
        [$coordinator] = $this->coordinator($nullTransactionsPolicy, AuditDelivery::Required);
        $nullTransactionsAttempt = $coordinator->evaluate(
            $this->metadata(CoordinatorTestPolicy::class, 'primary'),
            $this->envelope(new ActorRef('principal-1', 'user')),
        );
        try {
            $nullTransactionsAttempt->assertMutation($this->metadata(CoordinatorTestPolicy::class, 'primary'), null);
            self::fail('A missing transaction coordinator must be rejected.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        foreach ([
            ['autoCommit' => false, 'active' => false, 'level' => 0],
            ['autoCommit' => true, 'active' => true, 'level' => 0],
            ['autoCommit' => true, 'active' => false, 'level' => 1],
        ] as $state) {
            $policy = new CoordinatorTestPolicy();
            [$coordinator, , , $connection] = $this->coordinator($policy, AuditDelivery::Required);
            $attempt = $coordinator->evaluate(
                $this->metadata(CoordinatorTestPolicy::class, 'primary'),
                $this->envelope(new ActorRef('principal-1', 'user')),
            );
            $databases = $this->createMock(DatabaseManager::class);
            $databases->expects(self::once())->method('connection')->with('primary')->willReturn($connection);
            $transactions = new OperationTransactionCoordinator(
                new ReflectionClass(TransactionRuntime::class)->newInstanceWithoutConstructor(),
                $databases,
                $connection,
            );
            $connection->method('isAutoCommit')->willReturn($state['autoCommit']);
            $connection->method('isTransactionActive')->willReturn($state['active']);
            $connection->method('getTransactionNestingLevel')->willReturn($state['level']);

            try {
                $attempt->assertMutation($this->metadata(CoordinatorTestPolicy::class, 'primary'), $transactions);
                self::fail('An unsupported transaction root state must be rejected.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }

        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection] = $this->coordinator($policy, AuditDelivery::Required);
        $attempt = $coordinator->evaluate(
            $this->metadata(CoordinatorTestPolicy::class),
            $this->envelope(new ActorRef('principal-1', 'user')),
        );
        $databases = $this->createStub(DatabaseManager::class);
        $transactions = new OperationTransactionCoordinator(
            new ReflectionClass(TransactionRuntime::class)->newInstanceWithoutConstructor(),
            $databases,
            $connection,
        );
        try {
            $attempt->assertMutation($this->metadata(CoordinatorTestPolicy::class), $transactions);
            self::fail('Nontransactional metadata must be rejected.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testStandaloneDeliveryRequiresTheStoredDeliveryConnectionIdentity(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection, $factory, $selector] = $this->coordinator($policy, AuditDelivery::Durable);
        $envelope = $this->envelope(new ActorRef('principal-1', 'user'));
        $attempt = $coordinator->evaluate($this->metadata(CoordinatorTestPolicy::class), $envelope);
        $evaluation = $attempt->evaluation();
        $request = $factory->create($evaluation, $envelope->context());
        self::assertNotNull($request);
        $plan = $selector->forDraft($request->draft());
        $wrongConnection = $this->createStub(Connection::class);
        $wrongDelivery = new AuditDeliveryCoordinator(
            $wrongConnection,
            $selector,
            new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor(),
        );
        $miswired = AuthorizationAuditAttempt::prepare($evaluation, $request, $plan, $wrongDelivery, $connection);

        try {
            $miswired->deliverStandalone();
            self::fail('Standalone delivery must reject a different stored connection.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testStandaloneFailureConsumesPreparedAttemptBeforeAppend(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator] = $this->coordinator($policy, AuditDelivery::Durable, standalone: true);
        $attempt = $coordinator->evaluate(
            $this->metadata(CoordinatorTestPolicy::class),
            $this->envelope(new ActorRef('principal-1', 'user')),
        );

        try {
            $attempt->deliverStandalone();
            self::fail('Uninitialized storage must fail safely.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        try {
            $attempt->deliverStandalone();
            self::fail('A prepared attempt must be one-shot.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCoordinatorAndAttemptSerializationFailClosed(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator] = $this->coordinator($policy, AuditDelivery::Durable);
        self::assertSame([], $coordinator->__debugInfo());
        self::assertSame([], $coordinator->__serialize());

        try {
            unserialize(serialize($coordinator));
            self::fail('Coordinator unserialization must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }

        $attempt = $coordinator->evaluate($this->metadata(null), $this->envelope(null));
        self::assertSame([], $attempt->__debugInfo());
        self::assertSame([], $attempt->__serialize());
        try {
            unserialize(serialize($attempt));
            self::fail('Attempt unserialization must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        try {
            clone $attempt;
            self::fail('Attempt cloning must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
    }

    public function testCorruptCoordinatorAndAttemptSnapshotsFailClosed(): void
    {
        $policy = new CoordinatorTestPolicy();
        [$coordinator, , , $connection] = $this->coordinator($policy, AuditDelivery::Durable);
        $snapshots = [
            null,
            ['evaluator' => new \stdClass(), 'factory' => new \stdClass()],
            [
                'evaluator' => new \stdClass(),
                'factory' => new \stdClass(),
                'selector' => new \stdClass(),
                'delivery' => new \stdClass(),
                'connection' => new \stdClass(),
                'extra' => true,
            ],
        ];
        foreach ($snapshots as $snapshot) {
            $candidate = new ReflectionClass(AuthorizationAuditCoordinator::class)->newInstanceWithoutConstructor();
            if ($snapshot !== null) {
                new ReflectionProperty(AuthorizationAuditCoordinator::class, 'snapshot')->setValue(
                    $candidate,
                    new \SensitiveParameterValue($snapshot),
                );
            }
            try {
                $candidate->evaluate($this->metadata(null), $this->envelope(null));
                self::fail('Corrupt coordinator snapshots must fail closed.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }

        $attempt = $coordinator->evaluate($this->metadata(null), $this->envelope(null));
        $attemptSnapshots = [
            null,
            [
                'evaluation' => $attempt->evaluation(),
                'request' => null,
                'plan' => null,
                'delivery' => new \stdClass(),
                'connection' => $connection,
            ],
            [
                'evaluation' => $attempt->evaluation(),
                'request' => null,
                'plan' => null,
                'delivery' => new \stdClass(),
                'connection' => $connection,
                'extra' => true,
            ],
        ];
        foreach ($attemptSnapshots as $snapshot) {
            $candidate = new ReflectionClass(AuthorizationAuditAttempt::class)->newInstanceWithoutConstructor();
            if ($snapshot !== null) {
                new ReflectionProperty(AuthorizationAuditAttempt::class, 'snapshot')->setValue(
                    $candidate,
                    new \SensitiveParameterValue($snapshot),
                );
            }
            try {
                $candidate->deliverStandalone();
                self::fail('Corrupt attempt snapshots must fail closed.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    /** @return array{AuthorizationAuditCoordinator,CoordinatorTestClock,CoordinatorTestIdentifiers,Connection,AuthorizationAuditEventFactory,AuditDeliverySelector,AuditDeliveryCoordinator} */
    private function coordinator(
        CoordinatorTestPolicy $policy,
        AuditDelivery $delivery,
        bool $standalone = false,
        bool $mockConnection = false,
    ): array {
        $metadata = $this->metadata(CoordinatorTestPolicy::class);
        $binding = new AuthorizationAuditPolicyBinding($metadata, 'orders.authorization', 8, $delivery);
        $evaluator = new AuthorizationAuditEvaluator(
            new AuthorizationEvaluator(new AuthorizationPolicyResolver(new CoordinatorTestContainer($policy))),
            new AuthorizationAuditPolicyRegistry([$binding]),
        );
        $clock = new CoordinatorTestClock();
        $identifiers = new CoordinatorTestIdentifiers();
        $factory = new AuthorizationAuditEventFactory(
            new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
            $identifiers,
            $clock,
            \BlackOps\Core\Audit\AuditSourceRuntime::Http,
            'build-test',
            new ActorRef('fallback-1', 'worker'),
        );
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $connection = $mockConnection ? $this->createMock(Connection::class) : $this->createStub(Connection::class);
        if ($standalone) {
            $connection->method('isAutoCommit')->willReturn(true);
            $connection->method('isTransactionActive')->willReturn(false);
            $connection->method('getTransactionNestingLevel')->willReturn(0);
        }
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $deliveryCoordinator = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);

        return [
            new AuthorizationAuditCoordinator($evaluator, $factory, $selector, $deliveryCoordinator, $connection),
            $clock,
            $identifiers,
            $connection,
            $factory,
            $selector,
            $deliveryCoordinator,
        ];
    }

    private function draftWithPolicy(AuditRecordDraft $draft, AuditRecordPolicy $policy): AuditRecordDraft
    {
        return new AuditRecordDraft(
            $draft->event,
            $draft->action,
            $draft->actor,
            $draft->resource,
            $draft->outcome,
            $policy,
            $draft->tenant,
            $draft->occurredAt,
            $draft->source,
            $draft->correlation,
            $draft->evidence,
        );
    }

    private function metadata(?string $policy, ?string $transaction = null): OperationMetadata
    {
        return new OperationMetadata(
            'authorization.audit.coordinator',
            CoordinatorAuditOperation::class,
            CoordinatorAuditValue::class,
            CoordinatorAuditOperation::class,
            CoordinatorAuditOperation::class,
            Inline::class,
            authorizationPolicy: $policy,
            transactionConnection: $transaction,
        );
    }

    private function envelope(?ActorRef $authorization): OperationEnvelope
    {
        return new OperationEnvelope(
            new CoordinatorAuditOperation(),
            new CoordinatorAuditValue(),
            new ExecutionContext(
                OperationId::fromString(self::OPERATION_ID),
                new DateTimeImmutable('2026-07-17T00:00:00Z'),
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $authorization === null
                    ? null
                    : new ActorContext($authorization, $authorization, new ActorRef('worker-1', 'worker')),
            ),
            new Inline(),
        );
    }
}

final class CoordinatorTestClock implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-07-17T01:02:03.123456Z');
    }
}

final class CoordinatorTestIdentifiers implements Uuidv7Generator
{
    public int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return '019f32ad-2be0-7b38-a0a7-1ab2f9687697';
    }
}

final readonly class CoordinatorAuditOperation implements Operation {}

final readonly class CoordinatorAuditValue implements OperationValue {}

final class CoordinatorTestPolicy implements AuthorizationPolicy
{
    public int $calls = 0;

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        ++$this->calls;

        return AuthorizationDecision::allow();
    }
}

final class CoordinatorTestContainer implements ContainerInterface
{
    public function __construct(
        private readonly AuthorizationPolicy $policy,
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
