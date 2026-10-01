<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Core\TenantRef;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditCoordinator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEventFactory;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyRegistry;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\DirectClaimExecutionGuard;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\Execution\SupervisedHandlerFailureException;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Idempotency\IdempotencyReplayFailure;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\PostgreSqlIdempotencyStore;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator as StoreUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Journal\JournalEvent;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
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
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Exercises the native Inline, acceptance, and Worker delivery boundaries
 * against the real PostgreSQL journal, transport, audit stores, and policy
 * resolver.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:excessive-parameter-list
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuthorizationAuditRuntimeIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_authorization_audit';

    private const string OPERATION_ID = '019f9d20-0000-7000-8000-000000001001';

    private const string OPERATION_ID_2 = '019f9d20-0000-7000-8000-000000001002';

    private const string OPERATION_ID_3 = '019f9d20-0000-7000-8000-000000001003';

    private const string CORRELATION_ID = '019f9d20-0000-7000-8000-000000002001';

    private const string OPAQUE_KEY = '0123456789abcdef0123456789abcdef';

    private Connection $connection;

    private Connection $probe;

    private PostgreSqlDeferredOperationSender $sender;

    private PostgreSqlDeferredOperationReceiver $receiver;

    private PostgreSqlCanonicalJournalStore $journal;

    private PostgreSqlOutcomeStore $outcomes;

    private ReflectionJsonOperationCodec $codec;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->probe = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');

        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            new DateTimeImmutable('2026-09-16T00:00:01.000000Z'),
        );
        $this->receiver = new PostgreSqlDeferredOperationReceiver(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            'd4b-worker',
            30,
            new D4BClock(),
        );
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->outcomes = new PostgreSqlOutcomeStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->codec = new ReflectionJsonOperationCodec();
        $this->sender->migrate();
        $this->receiver->migrate();
        $this->journal->migrate();
        $this->outcomes->migrate();
        $this->connection->executeStatement(
            'CREATE TABLE "' . self::SCHEMA . '".business_updates ' . '(id BIGSERIAL PRIMARY KEY, value TEXT NOT NULL)',
        );

        $migration = new PostgreSqlMigrationSchema(self::SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($migration);
        $recordSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        foreach ([...$outboxSchema->statements(), ...$recordSchema->statements()] as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    protected function tearDown(): void
    {
        foreach ([$this->connection, $this->probe] as $connection) {
            try {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
            }
        }
        try {
            $this->probe->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        } catch (Throwable) {
        }
        foreach ([$this->connection, $this->probe] as $connection) {
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
    }

    /** @return array<string, list<AuditDelivery>> */
    public static function deliveryModes(): array
    {
        return [
            'required' => [AuditDelivery::Required],
            'durable' => [AuditDelivery::Durable],
        ];
    }

    #[DataProvider('deliveryModes')]
    public function testInlineAllowUsesTheAttributedRootBeforeTheHandler(AuditDelivery $delivery): void
    {
        $payloadCanary = 'inline-payload-canary-d4b';
        $observation = [];
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed, function () use (
            &$observation,
        ): void {
            $observation = [
                'active' => $this->connection->isTransactionActive(),
                'level' => $this->connection->getTransactionNestingLevel(),
                'frameworkAudit' => $this->auditCount($this->connection),
                'probeAudit' => $this->auditCount($this->probe),
                'business' => $this->businessCountOn($this->connection),
            ];
        });
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $audit = $this->audit(
            $metadata,
            new D4BPolicy(AuthorizationDecision::allow()),
            $delivery,
            AuditSourceRuntime::Http,
        );
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator']);

        $result = $dispatcher->dispatch(
            new D4BRuntimeOperation(),
            new D4BRuntimeValue($payloadCanary),
            $this->actors(),
            tenant: new TenantRef('account', 'tenant-d4b'),
        );

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertSame($payloadCanary, $handler->receivedValue);
        self::assertTrue($observation['active'] ?? false);
        self::assertSame(1, $observation['level'] ?? null);
        self::assertSame(1, $observation['frameworkAudit'] ?? null);
        self::assertSame(0, $observation['probeAudit'] ?? null);
        self::assertSame(0, $observation['business'] ?? null);
        self::assertSame(1, $this->businessCount());
        self::assertSame(1, $this->auditCountOnConnection($audit['coordinator']));
        $this->assertDeliveryCounts($delivery, 1);
        $this->assertAuditOmitsCanaries($audit['protection'], $delivery, $payloadCanary);
        self::assertSame(
            [
                JournalEvent::OperationReceived->value,
                JournalEvent::AttemptStarted->value,
                JournalEvent::AttemptSucceeded->value,
                JournalEvent::OperationCompleted->value,
            ],
            $this->journalEvents(),
        );
    }

    #[DataProvider('deliveryModes')]
    public function testInlineDenyCommitsStandaloneAuditBeforeTheOriginalReason(AuditDelivery $delivery): void
    {
        $payloadCanary = 'inline-deny-payload-canary-d4b';
        $rejectionCanary = 'public-rejection-canary-d4b';
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::forbid($rejectionCanary));
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, null);
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy);

        $result = $dispatcher->dispatch(
            new D4BRuntimeOperation(),
            new D4BRuntimeValue($payloadCanary),
            $this->actors(),
        );

        self::assertTrue($result->isRejected());
        self::assertSame(RejectionCategory::Forbidden, $result->rejectionReason()->category());
        self::assertSame($rejectionCanary, $result->rejectionReason()->code());
        self::assertSame($payloadCanary, $policy->lastRequestValue);
        self::assertSame($rejectionCanary, $policy->lastRejectionCode);
        self::assertSame(0, $handler->calls);
        self::assertSame(0, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 1);
        self::assertSame(
            [
                JournalEvent::OperationReceived->value,
                JournalEvent::AttemptStarted->value,
                JournalEvent::OperationRejected->value,
            ],
            $this->journalEvents(),
        );
        $this->assertEventType($audit['protection'], $delivery, 'security.authorization.deny');
        $this->assertAuditOmitsCanaries($audit['protection'], $delivery, $payloadCanary, $rejectionCanary);
    }

    #[DataProvider('deliveryModes')]
    public function testInlineRejectsUnsupportedRealPostgreSqlMutationRootsBeforeTheHandler(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::allow());
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy);

        $this->connection->beginTransaction();
        $ambientFailure = null;
        try {
            $dispatcher->dispatch(
                new D4BRuntimeOperation(),
                new D4BRuntimeValue('inline-ambient-root'),
                $this->actors(),
            );
            self::fail('An ambient PostgreSQL transaction must be rejected before the handler.');
        } catch (OperationExecutionFailed $failure) {
            $ambientFailure = $failure;
        }
        self::assertNotNull($ambientFailure);
        self::assertInstanceOf(AuditDeliveryException::class, $ambientFailure->primaryFailure());
        self::assertSame(AuditDeliveryFailure::TransactionInvalid, $ambientFailure->primaryFailure()->failure());
        self::assertSame(0, $handler->calls);
        $this->connection->rollBack();

        $this->connection->setAutoCommit(false);
        $this->connection->beginTransaction();
        $autoCommitFailure = null;
        try {
            $dispatcher->dispatch(
                new D4BRuntimeOperation(),
                new D4BRuntimeValue('inline-auto-commit-off'),
                $this->actors(),
            );
            self::fail('PostgreSQL auto-commit false must be rejected before the handler.');
        } catch (OperationExecutionFailed $failure) {
            $autoCommitFailure = $failure;
        }
        self::assertNotNull($autoCommitFailure);
        self::assertInstanceOf(AuditDeliveryException::class, $autoCommitFailure->primaryFailure());
        self::assertSame(AuditDeliveryFailure::TransactionInvalid, $autoCommitFailure->primaryFailure()->failure());
        self::assertSame(0, $handler->calls);
        self::assertFalse($this->connection->isAutoCommit());
        self::assertTrue($this->connection->isTransactionActive());
        self::assertSame(1, $this->connection->getTransactionNestingLevel());
        $this->connection->rollBack();
        $this->connection->setAutoCommit(true);

        self::assertTrue($this->connection->isAutoCommit());
        self::assertSame(0, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 0);
    }

    #[DataProvider('deliveryModes')]
    public function testDeferredAcceptanceRejectsAnAuditConnectionIdentityMismatch(AuditDelivery $delivery): void
    {
        self::assertNotSame($this->connection, $this->probe);
        self::assertSame(1, (int) $this->probe->fetchOne('SELECT 1'));
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $roots = [];
        $policy->onDecision = function () use (&$roots): void {
            $roots = [
                'framework' => $this->connection->isTransactionActive(),
                'audit' => $this->probe->isTransactionActive(),
            ];
        };
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, null);
        $this->probe->beginTransaction();
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http, connection: $this->probe);

        $failure = null;
        try {
            $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
            self::fail('An audit coordinator bound to another PostgreSQL connection must be rejected.');
        } catch (OperationExecutionFailed $caught) {
            $failure = $caught;
        }

        self::assertNotNull($failure);
        self::assertInstanceOf(AuditDeliveryException::class, $failure->primaryFailure());
        self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->primaryFailure()->failure());
        self::assertTrue($roots['framework'] ?? false);
        self::assertTrue($roots['audit'] ?? false);
        $this->probe->rollBack();
        self::assertSame(1, $policy->calls);
        self::assertSame(0, $handler->calls);
        self::assertSame(0, $this->businessCount());
        self::assertSame(0, $this->operationCount());
        $this->assertDeliveryCounts($delivery, 0);
    }

    #[DataProvider('deliveryModes')]
    public function testInlineBusinessRejectionRollsBackAllowAuditWithoutFabricatedDeny(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Rejected);
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy);

        $result = $dispatcher->dispatch(
            new D4BRuntimeOperation(),
            new D4BRuntimeValue('inline-rejected'),
            $this->actors(),
        );

        self::assertTrue($result->isRejected());
        self::assertSame('business.rejected', $result->rejectionReason()->code());
        self::assertSame(1, $handler->calls);
        self::assertSame(0, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 0);
        self::assertSame([], $this->eventTypes($audit['protection'], $delivery));
    }

    #[DataProvider('deliveryModes')]
    public function testInlineKeyedReplayAndConflictEachHaveOneEvaluationAndEvent(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $idempotency = new PostgreSqlIdempotencyStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $idempotency->migrate();
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy, $idempotency);
        $actors = $this->actors();
        $key = new IdempotencyKey('d4b-inline-key');

        $first = $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('same'), $actors, $key);
        $replay = $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('same'), $actors, $key);
        $conflict = $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('different'), $actors, $key);

        self::assertTrue($first->isCompleted());
        self::assertTrue($replay->isCompleted());
        self::assertTrue($replay->isReplayed());
        self::assertTrue($conflict->isRejected());
        self::assertSame('idempotency_conflict', $conflict->rejectionReason()->code());
        self::assertNull($conflict->operationId());
        self::assertSame(1, $handler->calls);
        self::assertSame(3, $policy->calls);
        $this->assertDeliveryCounts($delivery, 3);
    }

    #[DataProvider('deliveryModes')]
    public function testDeferredAcceptanceWritesAllowAndDenyInsideItsExistingRoot(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::forbid('accept.denied'));
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $allowMetadata = $this->metadata(Deferred::class, $handler::class, $delivery, null);
        $audit = $this->audit($allowMetadata, $policy, $delivery, AuditSourceRuntime::Http);
        $allow = $this->accept($allowMetadata, $policy, self::OPERATION_ID, $audit['coordinator']);

        self::assertInstanceOf(DeferredAcknowledgement::class, $allow);
        self::assertSame(1, $this->operationCount('accepted'));

        $denyMetadata = $this->metadata(Deferred::class, $handler::class, $delivery, null);
        $deny = $this->accept($denyMetadata, $policy, self::OPERATION_ID_2, $audit['coordinator']);

        self::assertInstanceOf(OperationResult::class, $deny);
        self::assertTrue($deny->isRejected());
        self::assertSame('accept.denied', $deny->rejectionReason()->code());
        self::assertSame(1, $this->operationCount('accepted'));
        $this->assertDeliveryCounts($delivery, 2);
        self::assertSame(
            ['security.authorization.allow', 'security.authorization.deny'],
            $this->eventTypes($audit['protection'], $delivery),
        );
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerAuthorizationDenyUsesRejectRoot(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::forbid('worker.denied'));
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);

        $result = $this->worker($metadata, $handler, $policy, $audit['coordinator'])->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame('worker.denied', $result->rejectionReason()->code());
        self::assertSame(0, $handler->calls);
        self::assertSame('rejected', $this->operationState());
        self::assertSame(2, $policy->calls);
        $this->assertDeliveryCounts($delivery, 2);
        $this->assertEventType($audit['protection'], $delivery, 'security.authorization.deny');
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerBusinessRejectionRollsBackItsAllowAudit(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::allow());
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Rejected);
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);

        $result = $this->worker($metadata, $handler, $policy, $audit['coordinator'])->run($claim);

        self::assertTrue($result->isRejected());
        self::assertSame('business.rejected', $result->rejectionReason()->code());
        self::assertSame(1, $handler->calls);
        self::assertSame(0, $this->businessCount());
        self::assertSame('rejected', $this->operationState());
        $this->assertDeliveryCounts($delivery, 1);
        self::assertSame(['security.authorization.allow'], $this->eventTypes($audit['protection'], $delivery));
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerAllowPreservesCompletionFencingJournalAndOutcome(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::allow());
        $observation = [];
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed, function () use (
            &$observation,
        ): void {
            $observation = [
                'active' => $this->connection->isTransactionActive(),
                'level' => $this->connection->getTransactionNestingLevel(),
                'frameworkAudit' => $this->auditCount($this->connection),
                'probeAudit' => $this->auditCount($this->probe),
                'business' => $this->businessCountOn($this->connection),
            ];
        });
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);

        $result = $this->worker($metadata, $handler, $policy, $audit['coordinator'])->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertTrue($observation['active'] ?? false);
        self::assertSame(1, $observation['level'] ?? null);
        self::assertSame(2, $observation['frameworkAudit'] ?? null);
        self::assertSame(1, $observation['probeAudit'] ?? null);
        self::assertSame(0, $observation['business'] ?? null);
        self::assertSame(1, $this->businessCount());
        self::assertSame('completed', $this->operationState());
        self::assertSame(1, $this->outcomeCount());
        self::assertSame(
            [
                JournalEvent::OperationReceived->value,
                JournalEvent::OperationAccepted->value,
                JournalEvent::AttemptStarted->value,
                JournalEvent::AttemptSucceeded->value,
                JournalEvent::OperationCompleted->value,
            ],
            $this->journalEvents(),
        );
        $this->assertDeliveryCounts($delivery, 2);
        $actionIds = $this->actionIds($audit['protection'], $delivery);
        self::assertCount(2, $actionIds);
        self::assertCount(2, array_unique($actionIds));
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerRetryReevaluatesAndCommitsOnlyTheFreshAllow(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(
            AuthorizationDecision::allow(),
            AuthorizationDecision::allow(),
            AuthorizationDecision::allow(),
        );
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Retryable);
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
        $firstClaim = $this->claim();
        self::assertNotNull($firstClaim);
        $runtime = $this->worker($metadata, $handler, $policy, $audit['coordinator']);

        try {
            $runtime->run($firstClaim);
            self::fail('The first retryable Worker attempt must be supervised.');
        } catch (SupervisedHandlerFailureException) {
            self::assertSame('retry_scheduled', $this->operationState());
        }

        $secondClaim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-09-16T00:10:00.000000Z')));
        self::assertNotNull($secondClaim);
        $result = $runtime->run($secondClaim);

        self::assertTrue($result->isCompleted());
        self::assertSame(2, $handler->calls);
        self::assertSame(3, $policy->calls);
        self::assertSame('completed', $this->operationState());
        self::assertSame(1, $this->outcomeCount());
        $this->assertDeliveryCounts($delivery, 2);
        $actionIds = $this->actionIds($audit['protection'], $delivery);
        self::assertCount(2, $actionIds);
        self::assertCount(2, array_unique($actionIds));
    }

    #[DataProvider('deliveryModes')]
    public function testNoPolicyLeavesNativeRuntimeAndAuditStoresUntouched(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $metadata = $this->metadata(Inline::class, $handler::class, null, 'app');
        $audit = $this->audit($metadata, null, $delivery, AuditSourceRuntime::Http);
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator']);

        $result = $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('no-policy'), $this->actors());

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertSame(1, $this->businessCount());
        self::assertDeliveryCounts($delivery, 0);
    }

    #[DataProvider('deliveryModes')]
    public function testNoPolicyAlsoLeavesDeferredAcceptanceAndWorkerUntouched(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $metadata = $this->metadata(Deferred::class, $handler::class, null, 'app');
        $audit = $this->audit($metadata, null, $delivery, AuditSourceRuntime::Worker);
        $accepted = $this->accept($metadata, null, self::OPERATION_ID, $audit['coordinator']);

        self::assertInstanceOf(DeferredAcknowledgement::class, $accepted);
        self::assertSame(1, $this->operationCount('accepted'));
        $claim = $this->claim();
        self::assertNotNull($claim);
        $result = $this->worker($metadata, $handler, null, $audit['coordinator'])->run($claim);

        self::assertTrue($result->isCompleted());
        self::assertSame(1, $handler->calls);
        self::assertSame('completed', $this->operationState());
        self::assertDeliveryCounts($delivery, 0);
    }

    #[DataProvider('deliveryModes')]
    public function testDeferredAcceptanceKeyedReplayAndConflictEachHaveOneEvaluationAndEvent(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Deferred::class, D4BRuntimeHandler::class, $delivery, null);
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $idempotency = new PostgreSqlIdempotencyStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $idempotency->migrate();
        $key = new IdempotencyKey('d4b-accept-key');

        $first = $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator'], $key, $idempotency);
        $replay = $this->accept($metadata, $policy, self::OPERATION_ID_2, $audit['coordinator'], $key, $idempotency);
        $conflict = $this->accept(
            $metadata,
            $policy,
            self::OPERATION_ID_3,
            $audit['coordinator'],
            $key,
            $idempotency,
            value: 'different',
        );

        self::assertInstanceOf(DeferredAcknowledgement::class, $first);
        self::assertInstanceOf(DeferredAcknowledgement::class, $replay);
        self::assertTrue($replay->isReplayed());
        self::assertInstanceOf(OperationResult::class, $conflict);
        self::assertTrue($conflict->isRejected());
        self::assertSame('idempotency_conflict', $conflict->rejectionReason()->code());
        self::assertSame(3, $policy->calls);
        $this->assertDeliveryCounts($delivery, 3);
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerDenyDeliveryFailureRollsBackRejectRootAndSupervises(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::forbid('worker.denied'));
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
        if ($delivery === AuditDelivery::Required) {
            $this->connection->executeStatement('DROP TABLE "' . self::SCHEMA . '".audit_records CASCADE');
        } else {
            $this->connection->executeStatement('DROP TABLE "' . self::SCHEMA . '".audit_outbox_records CASCADE');
        }
        $claim = $this->claim();
        self::assertNotNull($claim);

        try {
            $this->worker($metadata, $handler, $policy, $audit['coordinator'])->run($claim);
            self::fail('A Worker deny delivery failure must be supervised.');
        } catch (SupervisedHandlerFailureException) {
            self::assertSame('failed', $this->operationState());
            self::assertSame(0, $handler->calls);
            self::assertSame(0, $this->businessCount());
        }
    }

    public function testInlineAuditOutageWithholdsHandlerAndNormalResult(): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Inline::class, $handler::class, AuditDelivery::Required, 'app');
        $audit = $this->audit($metadata, $policy, AuditDelivery::Required, AuditSourceRuntime::Http);
        $this->connection->executeStatement('DROP TABLE "' . self::SCHEMA . '".audit_records CASCADE');
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy);

        try {
            $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('outage'), $this->actors());
            self::fail('An audit outage must withhold the inline result.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame(0, $handler->calls);
            self::assertSame(0, $this->businessCount());
            self::assertSame(0, $this->operationCount());
            self::assertNotNull($failure->primaryFailure());
        }
    }

    #[DataProvider('deliveryModes')]
    public function testInlineHandlerFailureRollsBackAllowAuditAndBusinessMutation(AuditDelivery $delivery): void
    {
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Throws);
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy);

        try {
            $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('throw'), $this->actors());
            self::fail('A handler exception must fail the inline execution.');
        } catch (OperationExecutionFailed $failure) {
            self::assertNotNull($failure->primaryFailure());
            self::assertSame(1, $handler->calls);
        }

        self::assertSame(0, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 0);
        self::assertSame(
            [
                JournalEvent::OperationReceived->value,
                JournalEvent::AttemptStarted->value,
                JournalEvent::AttemptFailed->value,
                JournalEvent::OperationFailed->value,
            ],
            $this->journalEvents(),
        );
    }

    #[DataProvider('deliveryModes')]
    public function testDeferredAcceptanceAuditOutageRollsBackTheExistingAcceptanceRoot(AuditDelivery $delivery): void
    {
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $metadata = $this->metadata(Deferred::class, $handler::class, $delivery, null);
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        if ($delivery === AuditDelivery::Required) {
            $this->connection->executeStatement('DROP TABLE "' . self::SCHEMA . '".audit_records CASCADE');
        } else {
            $this->connection->executeStatement('DROP TABLE "' . self::SCHEMA . '".audit_outbox_records CASCADE');
        }

        try {
            $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
            self::fail('An acceptance audit outage must fail before enqueue.');
        } catch (OperationExecutionFailed $failure) {
            self::assertNotNull($failure->primaryFailure());
        }

        self::assertSame(0, $this->operationCount());
        self::assertSame(0, $this->businessCount());
    }

    #[DataProvider('deliveryModes')]
    public function testInlineCommitThenThrowLeavesCommittedRowsAndInternalFailureReplayFence(AuditDelivery $delivery): void
    {
        $fault = $this->swapToCommitFault();
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Inline::class, $handler::class, $delivery, 'app');
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $idempotency = new PostgreSqlIdempotencyStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $idempotency->migrate();
        $dispatcher = $this->inline($metadata, $handler, $audit['coordinator'], $policy, $idempotency);
        $key = new IdempotencyKey('d4b-ambiguous-inline');
        $fault->arm();

        try {
            $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('ambiguous'), $this->actors(), $key);
            self::fail('A commit response loss must withhold the inline result.');
        } catch (OperationExecutionFailed $failure) {
            self::assertNotNull($failure->primaryFailure());
        }

        self::assertSame(1, $handler->calls);
        self::assertSame(1, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 1);
        self::assertSame(
            [
                JournalEvent::OperationReceived->value,
                JournalEvent::AttemptStarted->value,
                JournalEvent::AttemptSucceeded->value,
                JournalEvent::OperationCompleted->value,
                JournalEvent::AttemptFailed->value,
                JournalEvent::OperationFailed->value,
            ],
            $this->journalEvents(),
        );
        $scope = new IdempotencyScopeHasher()->hash(
            'd4b.runtime.operation',
            new ActorRef('principal-d4b', 'user'),
            $key,
        );
        $record = $idempotency->find($scope);
        self::assertInstanceOf(TerminalRecord::class, $record);
        self::assertNotNull($record->result());
        self::assertTrue($record->result()?->isInternalFailure());

        try {
            $dispatcher->dispatch(new D4BRuntimeOperation(), new D4BRuntimeValue('ambiguous'), $this->actors(), $key);
            self::fail('The same-key replay must remain fenced after an ambiguous commit.');
        } catch (IdempotencyReplayFailure $failure) {
            self::assertSame(1, $handler->calls);
            self::assertSame(1, $this->auditCountOnConnection($audit['coordinator']));
            self::assertNotNull($failure);
        }
    }

    #[DataProvider('deliveryModes')]
    public function testDeferredAcceptanceCommitThenThrowRetainsEnqueueAndAuditWithoutSecondEnqueue(AuditDelivery $delivery): void
    {
        $fault = $this->swapToCommitFault();
        $policy = new D4BPolicy(AuthorizationDecision::allow());
        $metadata = $this->metadata(Deferred::class, D4BRuntimeHandler::class, $delivery, null);
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Http);
        $fault->arm();

        try {
            $this->accept($metadata, $policy, self::OPERATION_ID, $audit['coordinator']);
            self::fail('A commit response loss must withhold the acceptance acknowledgement.');
        } catch (OperationExecutionFailed $failure) {
            self::assertNotNull($failure->primaryFailure());
            self::assertFalse($failure->journalRecorded());
            self::assertNotNull($failure->recordingFailure());
        }

        self::assertSame(1, $this->operationCount('accepted'));
        self::assertSame(1, $this->transportCount());
        $this->assertDeliveryCounts($delivery, 1);
        self::assertContains(JournalEvent::OperationAccepted->value, $this->journalEvents());
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerCompletionCommitThenThrowKeepsTerminalFenceAndDoesNotRerun(AuditDelivery $delivery): void
    {
        $acceptHandler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::allow());
        $metadata = $this->metadata(Deferred::class, $acceptHandler::class, $delivery, 'app');
        $acceptAudit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $acceptAudit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);
        $fault = $this->swapToCommitFault();
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed, static fn() => $fault->arm());
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $runtime = $this->worker($metadata, $handler, $policy, $audit['coordinator']);

        $failure = null;
        try {
            $runtime->run($claim);
        } catch (Throwable $caught) {
            $failure = $caught;
        }
        self::assertInstanceOf(Throwable::class, $failure);

        self::assertSame(1, $handler->calls);
        self::assertSame(1, $this->businessCount());
        self::assertSame('completed', $this->operationState());
        self::assertSame(1, $this->outcomeCount());
        $this->assertDeliveryCounts($delivery, 2);
        $secondFailure = null;
        try {
            $runtime->run($claim);
        } catch (Throwable $caught) {
            $secondFailure = $caught;
        }
        self::assertInstanceOf(Throwable::class, $secondFailure);
        self::assertSame(1, $handler->calls);
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerDenyCommitThenThrowKeepsGenericClassificationAfterCommit(AuditDelivery $delivery): void
    {
        $acceptHandler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::forbid('worker.denied'));
        $metadata = $this->metadata(Deferred::class, $acceptHandler::class, $delivery, 'app');
        $acceptAudit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $acceptAudit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);
        $fault = $this->swapToCommitFault();
        $policy->onDecision = static function (int $calls) use ($fault): void {
            if ($calls === 2) {
                $fault->arm();
            }
        };
        $handler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);

        $failure = null;
        try {
            $this->worker($metadata, $handler, $policy, $audit['coordinator'])->run($claim);
        } catch (Throwable $caught) {
            $failure = $caught;
        }
        self::assertInstanceOf(Throwable::class, $failure);
        self::assertNotInstanceOf(SupervisedHandlerFailureException::class, $failure);

        self::assertSame('rejected', $this->operationState());
        self::assertSame(0, $handler->calls);
        $this->assertDeliveryCounts($delivery, 2);
    }

    #[DataProvider('deliveryModes')]
    public function testWorkerBusinessRejectionAuditExceptionAfterRejectCommitKeepsGenericClassification(AuditDelivery $delivery): void
    {
        $acceptHandler = new D4BRuntimeHandler($this->connection, D4BHandlerResult::Completed);
        $policy = new D4BPolicy(AuthorizationDecision::allow(), AuthorizationDecision::allow());
        $metadata = $this->metadata(Deferred::class, $acceptHandler::class, $delivery, 'app');
        $acceptAudit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $this->accept($metadata, $policy, self::OPERATION_ID, $acceptAudit['coordinator']);
        $claim = $this->claim();
        self::assertNotNull($claim);

        $fault = $this->swapToCommitFault();
        $handler = new D4BRuntimeHandler(
            $this->connection,
            D4BHandlerResult::Rejected,
            static fn() => $fault->arm(new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable)),
        );
        $audit = $this->audit($metadata, $policy, $delivery, AuditSourceRuntime::Worker);
        $runtime = $this->worker($metadata, $handler, $policy, $audit['coordinator']);

        $failure = null;
        try {
            $runtime->run($claim);
        } catch (Throwable $caught) {
            $failure = $caught;
        }
        self::assertInstanceOf(AuditDeliveryException::class, $failure);
        self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        self::assertSame('rejected', $this->operationState());
        self::assertSame(1, $handler->calls);
        self::assertSame(0, $this->businessCount());
        $this->assertDeliveryCounts($delivery, 1);
    }

    /**
     * @return array{coordinator:AuthorizationAuditCoordinator,protection:AuditProtectedBodyCodec}
     */
    private function audit(
        OperationMetadata $metadata,
        ?AuthorizationPolicy $policy,
        AuditDelivery $delivery,
        AuditSourceRuntime $runtime,
        ?Connection $connection = null,
    ): array {
        $connection ??= $this->connection;
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $validator = new AuditRecordValidator($lookup);
        $recordsCodec = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $recordsCodec);
        $envelopes = new AuditIngressEnvelopeCodec($recordsCodec, $partitions, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new D4BAuditKeySource(),
            new D4BAuditNonceSource(),
            new AuditAssociatedData(),
            $recordsCodec,
            $envelopes,
        );
        $migration = new PostgreSqlMigrationSchema(self::SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($migration);
        $recordSchema = new PostgreSqlAuditSchema(self::SCHEMA);
        $clock = new D4BClock();
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $recordSchema,
            $clock,
            new D4BStoreUuidv7Generator(),
            $validator,
            $finalizer,
            $partitions,
            $recordsCodec,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $connection,
            $outboxSchema,
            $clock,
            new D4BStoreUuidv7Generator(),
            $validator,
            $finalizer,
            $partitions,
            $envelopes,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(
                new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator(),
            ),
            new AuditOutboxConfiguration(),
        );
        $deliveryCoordinator = new AuditDeliveryCoordinator(
            $connection,
            new AuditDeliverySelector($lookup, $validator),
            $recordStore,
            $outboxStore,
        );
        $container = new D4BContainer(policy: $policy);
        $evaluator = new AuthorizationAuditEvaluator(
            new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
            new AuthorizationAuditPolicyRegistry(
                $metadata->authorizationPolicy === null
                    ? []
                    : [new AuthorizationAuditPolicyBinding($metadata, 'd4b.authorization', 7, $delivery)],
            ),
        );
        $factory = new AuthorizationAuditEventFactory(
            new HmacAuditOpaqueIdDeriver(self::OPAQUE_KEY),
            new D4BPublicUuidv7Generator(),
            $clock,
            $runtime,
            'd4b-native-test',
            new ActorRef('fallback-worker', 'system'),
        );

        return [
            'coordinator' => new AuthorizationAuditCoordinator(
                $evaluator,
                $factory,
                new AuditDeliverySelector($lookup, $validator),
                $deliveryCoordinator,
                $connection,
            ),
            'protection' => $protection,
        ];
    }

    private function inline(
        OperationMetadata $metadata,
        D4BRuntimeHandler $handler,
        AuthorizationAuditCoordinator $audit,
        ?AuthorizationPolicy $policy = null,
        ?IdempotencyStore $idempotency = null,
    ): InlineDispatcher {
        $clock = new D4BClock();
        $ids = new D4BStoreUuidv7Generator();
        $identifiers = new IdentifierFactory($ids, $clock);
        $container = new D4BContainer($handler, $policy);
        $manager = $this->manager($this->connection);
        $scope = new ExecutionScopeProvider();
        $transactions = new OperationTransactionCoordinator(
            new TransactionRuntime($manager, new D4BAfterCommitReporter(), $scope),
            $manager,
            $this->connection,
        );

        return new InlineDispatcher(
            new \BlackOps\Core\Registry\OperationRegistry([$metadata]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new \BlackOps\Internal\Journal\JournalRecordFactory($identifiers, $clock),
            $this->journal,
            transactions: $transactions,
            authorization: new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
            idempotency: $idempotency,
            idempotencyRetention: \BlackOps\Core\Retention\RetentionPeriod::days(3),
            authorizationAudit: $audit,
        );
    }

    private function accept(
        OperationMetadata $metadata,
        ?AuthorizationPolicy $policy,
        string $operationId,
        AuthorizationAuditCoordinator $audit,
        ?IdempotencyKey $key = null,
        ?IdempotencyStore $idempotency = null,
        string $value = 'deferred',
    ): DeferredAcknowledgement|OperationResult {
        $context = new ExecutionContext(
            OperationId::fromString($operationId),
            new DateTimeImmutable('2026-09-16T00:00:00.000000Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: $this->actors(),
            idempotencyKeyHash: $key?->hash(),
            tenant: new TenantRef('account', 'tenant-d4b'),
        );
        $operationValue = new D4BRuntimeValue($value);
        $envelope = new OperationEnvelope(new D4BRuntimeOperation(), $operationValue, $context, new Deferred());
        $encoded = $this->codec->encode($metadata, $operationValue, $context);
        $message = new DeferredOperationMessage(
            $context->operationId(),
            $encoded->operationType(),
            $encoded->schemaVersion(),
            $encoded->encodedPayload(),
            $encoded->encodedContext(),
            $context->receivedAt(),
            tenant: $context->tenant(),
            originActor: $context->actorContext()?->origin(),
        );
        $clock = new D4BClock();
        $identifiers = new IdentifierFactory(new D4BStoreUuidv7Generator(), $clock);
        return new DeferredAcceptanceOrchestrator(
            $this->connection,
            $this->sender,
            $this->journal,
            new \BlackOps\Internal\Journal\JournalRecordFactory($identifiers, $clock),
            authorization: $policy === null
                ? null
                : new AuthorizationEvaluator(new AuthorizationPolicyResolver(new D4BContainer(policy: $policy))),
            idempotency: $idempotency,
            idempotencyRetention: \BlackOps\Core\Retention\RetentionPeriod::days(3),
            authorizationAudit: $audit,
        )->accept($message, $envelope, $metadata);
    }

    private function worker(
        OperationMetadata $metadata,
        D4BRuntimeHandler $handler,
        ?AuthorizationPolicy $policy,
        AuthorizationAuditCoordinator $audit,
    ): DeferredWorkerRuntime {
        $clock = new D4BClock();
        $ids = new D4BStoreUuidv7Generator();
        $identifiers = new IdentifierFactory($ids, $clock);
        $container = new D4BContainer($handler, $policy);
        $manager = $this->manager($this->connection);
        $scope = new ExecutionScopeProvider();
        $transactions = new OperationTransactionCoordinator(
            new TransactionRuntime($manager, new D4BAfterCommitReporter(), $scope),
            $manager,
            $this->connection,
        );
        $services = new DeferredWorkerRuntimeServices(
            new \BlackOps\Core\Registry\OperationRegistry([$metadata]),
            $this->codec,
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new ActorRef('d4b-worker', 'system'),
            new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
        );
        $storage = new DeferredWorkerRuntimeStorage(
            $this->connection,
            new \BlackOps\Internal\Journal\JournalRecordFactory($identifiers, $clock),
            $this->journal,
            new PostgreSqlDeferredOperationLifecycleStore(
                $this->connection,
                PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
            $clock,
            $this->outcomes,
            scope: $scope,
            transactions: $transactions,
        );

        return new DeferredWorkerRuntime(
            $services,
            $storage,
            new DirectClaimExecutionGuard(),
            authorizationAudit: $audit,
        );
    }

    private function metadata(
        string $strategy,
        string $handler,
        ?AuditDelivery $delivery,
        ?string $transaction,
    ): OperationMetadata {
        return new OperationMetadata(
            'd4b.runtime.operation',
            D4BRuntimeOperation::class,
            D4BRuntimeValue::class,
            $handler,
            EmptyOutcome::class,
            $strategy,
            authorizationPolicy: $delivery === null ? null : D4BPolicy::class,
            transactionConnection: $transaction,
        );
    }

    private function claim(): ?OperationClaim
    {
        return $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-09-16T00:01:00.000000Z')));
    }

    private function swapToCommitFault(): D4BCommitThenThrowingConnection
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $connection = new D4BCommitThenThrowingConnection($params, $driver);
        $this->connection = $connection;
        $this->sender = new PostgreSqlDeferredOperationSender(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            new DateTimeImmutable('2026-09-16T00:00:01.000000Z'),
        );
        $this->receiver = new PostgreSqlDeferredOperationReceiver(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            'd4b-worker',
            30,
            new D4BClock(),
        );
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->outcomes = new PostgreSqlOutcomeStore(
            $connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );

        return $connection;
    }

    private function actors(): ActorContext
    {
        $actor = new ActorRef('principal-d4b', 'user');

        return new ActorContext($actor, $actor, new ActorRef('http-d4b', 'system'));
    }

    private function manager(Connection $connection): DatabaseManager
    {
        return new class($connection) implements DatabaseManager {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function connection(?string $name = null): Connection
            {
                if ($name !== null && $name !== 'app') {
                    throw new RuntimeException('Unexpected database connection name.');
                }

                return $this->connection;
            }
        };
    }

    private function businessCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".business_updates');
    }

    private function operationCount(?string $state = null): int
    {
        $sql = 'SELECT count(*) FROM "' . self::SCHEMA . '".operations';
        if ($state !== null) {
            $sql .= ' WHERE state = :state';
        }

        return (int) $this->probe->fetchOne($sql, $state === null ? [] : ['state' => $state]);
    }

    private function operationState(): string
    {
        return (string) $this->probe->fetchOne('SELECT state FROM "' . self::SCHEMA . '".operations');
    }

    private function transportCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".operations');
    }

    private function outcomeCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".outcomes');
    }

    /** @return list<string> */
    private function journalEvents(): array
    {
        return array_map(
            static fn(mixed $event): string => (string) $event,
            $this->probe->fetchFirstColumn('SELECT event FROM "' . self::SCHEMA . '".journal ORDER BY sequence'),
        );
    }

    private function auditCountOnConnection(AuthorizationAuditCoordinator $audit): int
    {
        unset($audit);

        return $this->auditCount($this->probe);
    }

    private function auditCount(Connection $connection): int
    {
        return (
            (int) $connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".audit_records')
            + (int) $connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".audit_outbox_records')
        );
    }

    private function businessCountOn(Connection $connection): int
    {
        return (int) $connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".business_updates');
    }

    private function assertDeliveryCounts(AuditDelivery $delivery, int $count): void
    {
        $records = (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".audit_records');
        $outbox = (int) $this->probe->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '".audit_outbox_records');
        if ($delivery === AuditDelivery::Required) {
            self::assertSame($count, $records);
            self::assertSame(0, $outbox);
        } else {
            self::assertSame(0, $records);
            self::assertSame($count, $outbox);
        }
    }

    /** @return list<string> */
    private function eventTypes(AuditProtectedBodyCodec $protection, AuditDelivery $delivery): array
    {
        if ($delivery === AuditDelivery::Required) {
            return array_map(
                static fn(mixed $event): string => (string) $event,
                $this->probe->fetchFirstColumn(
                    'SELECT action_type FROM "' . self::SCHEMA . '".audit_records ORDER BY sequence',
                ),
            );
        }

        $rows = $this->probe->fetchAllAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'ingress_ordinal, idempotency_key_hash, content_hash, protected_body '
            . 'FROM "'
            . self::SCHEMA
            . '".audit_outbox_records ORDER BY ingress_ordinal',
        );
        $events = [];
        foreach ($rows as $row) {
            $tenant = $row['tenant_type'] === null ? null : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];
            $ingress = AuditProtectedIngress::fromArray([
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => $tenant,
                'ingressOrdinal' => (int) $row['ingress_ordinal'],
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'protectedBody' => PostgreSqlBytea::string($row['protected_body']),
            ]);
            $logical = $protection->unprotectOutbox($ingress)->toArray()['logicalRecord'];
            $events[] = (string) $logical['event']['type'];
        }

        return $events;
    }

    private function assertEventType(
        AuditProtectedBodyCodec $protection,
        AuditDelivery $delivery,
        string $expected,
    ): void {
        self::assertContains($expected, $this->eventTypes($protection, $delivery));
    }

    /** @return list<string> */
    private function actionIds(AuditProtectedBodyCodec $protection, AuditDelivery $delivery): array
    {
        if ($delivery === AuditDelivery::Durable) {
            $rows = $this->probe->fetchAllAssociative(
                'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
                . 'ingress_ordinal, idempotency_key_hash, content_hash, protected_body '
                . 'FROM "'
                . self::SCHEMA
                . '".audit_outbox_records ORDER BY ingress_ordinal',
            );
            $ids = [];
            foreach ($rows as $row) {
                $tenant = $row['tenant_type'] === null
                    ? null
                    : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];
                $ingress = AuditProtectedIngress::fromArray([
                    'recordId' => $row['record_id'],
                    'partitionId' => $row['partition_id'],
                    'tenantBinding' => $tenant,
                    'ingressOrdinal' => (int) $row['ingress_ordinal'],
                    'idempotencyKeyHash' => $row['idempotency_key_hash'],
                    'contentHash' => $row['content_hash'],
                    'protectedBody' => PostgreSqlBytea::string($row['protected_body']),
                ]);
                $logical = $protection->unprotectOutbox($ingress)->toArray()['logicalRecord'];
                $action = $logical['action'] ?? null;
                if (!is_array($action) || !is_string($action['id'] ?? null)) {
                    throw new RuntimeException('D4B audit action is malformed.');
                }
                $ids[] = $action['id'];
            }

            return $ids;
        }

        $rows = $this->probe->fetchAllAssociative(
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, '
            . 'tenant_type, tenant_id, segment_id::text AS segment_id, sequence::text AS sequence, '
            . 'previous_record_hash, content_hash, record_hash, '
            . 'to_char(recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS recorded_at, '
            . 'event_category, protected_body '
            . 'FROM "'
            . self::SCHEMA
            . '".audit_records ORDER BY sequence',
        );
        $hydrator = new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec()));
        $ids = [];
        foreach ($rows as $row) {
            $stored = $hydrator->hydrate([
                'schema_version' => $row['schema_version'],
                'kind' => $row['kind'],
                'record_id' => $row['record_id'],
                'partition_id' => $row['partition_id'],
                'tenant_type' => $row['tenant_type'],
                'tenant_id' => $row['tenant_id'],
                'segment_id' => $row['segment_id'],
                'sequence' => $row['sequence'],
                'previous_record_hash' => $row['previous_record_hash'],
                'content_hash' => $row['content_hash'],
                'record_hash' => $row['record_hash'],
                'recorded_at' => $row['recorded_at'],
                'event_category' => $row['event_category'],
                'protected_body' => $row['protected_body'],
            ]);
            $tenant = $row['tenant_type'] === null
                ? null
                : new AuditTypedReference((string) $row['tenant_type'], (string) $row['tenant_id']);
            $logical = $protection->unprotectRecord($stored, $tenant, (string) $row['partition_id'])->logicalSnapshot();
            $action = $logical['action'] ?? null;
            if (!is_array($action) || !is_string($action['id'] ?? null)) {
                throw new RuntimeException('D4B audit action is malformed.');
            }
            $ids[] = $action['id'];
        }

        return $ids;
    }

    /** @return list<array<string, mixed>> */
    private function logicalSnapshots(AuditProtectedBodyCodec $protection, AuditDelivery $delivery): array
    {
        if ($delivery === AuditDelivery::Durable) {
            $rows = $this->probe->fetchAllAssociative(
                'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
                . 'ingress_ordinal, idempotency_key_hash, content_hash, protected_body '
                . 'FROM "'
                . self::SCHEMA
                . '".audit_outbox_records ORDER BY ingress_ordinal',
            );
            $snapshots = [];
            foreach ($rows as $row) {
                $tenant = $row['tenant_type'] === null
                    ? null
                    : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];
                $ingress = AuditProtectedIngress::fromArray([
                    'recordId' => $row['record_id'],
                    'partitionId' => $row['partition_id'],
                    'tenantBinding' => $tenant,
                    'ingressOrdinal' => (int) $row['ingress_ordinal'],
                    'idempotencyKeyHash' => $row['idempotency_key_hash'],
                    'contentHash' => $row['content_hash'],
                    'protectedBody' => PostgreSqlBytea::string($row['protected_body']),
                ]);
                $logical = $protection->unprotectOutbox($ingress)->toArray()['logicalRecord'];
                if (!is_array($logical)) {
                    throw new RuntimeException('D4B audit logical record is malformed.');
                }
                $snapshots[] = $logical;
            }

            return $snapshots;
        }

        $rows = $this->probe->fetchAllAssociative(
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, '
            . 'tenant_type, tenant_id, segment_id::text AS segment_id, sequence::text AS sequence, '
            . 'previous_record_hash, content_hash, record_hash, '
            . 'to_char(recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS recorded_at, '
            . 'event_category, protected_body '
            . 'FROM "'
            . self::SCHEMA
            . '".audit_records ORDER BY sequence',
        );
        $hydrator = new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec()));
        $snapshots = [];
        foreach ($rows as $row) {
            $stored = $hydrator->hydrate([
                'schema_version' => $row['schema_version'],
                'kind' => $row['kind'],
                'record_id' => $row['record_id'],
                'partition_id' => $row['partition_id'],
                'tenant_type' => $row['tenant_type'],
                'tenant_id' => $row['tenant_id'],
                'segment_id' => $row['segment_id'],
                'sequence' => $row['sequence'],
                'previous_record_hash' => $row['previous_record_hash'],
                'content_hash' => $row['content_hash'],
                'record_hash' => $row['record_hash'],
                'recorded_at' => $row['recorded_at'],
                'event_category' => $row['event_category'],
                'protected_body' => $row['protected_body'],
            ]);
            $tenant = $row['tenant_type'] === null
                ? null
                : new AuditTypedReference((string) $row['tenant_type'], (string) $row['tenant_id']);
            $snapshots[] = $protection
                ->unprotectRecord($stored, $tenant, (string) $row['partition_id'])
                ->logicalSnapshot();
        }

        return $snapshots;
    }

    private function assertAuditOmitsCanaries(
        AuditProtectedBodyCodec $protection,
        AuditDelivery $delivery,
        string ...$canaries,
    ): void {
        foreach ($this->logicalSnapshots($protection, $delivery) as $logical) {
            $encoded = json_encode($logical, JSON_THROW_ON_ERROR);
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString($canary, $encoded);
            }
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

/** @mago-expect lint:single-class-per-file */
final readonly class D4BRuntimeOperation implements Operation {}

/** @mago-expect lint:single-class-per-file */
final readonly class D4BRuntimeValue implements OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}

/** @mago-expect lint:single-class-per-file */
enum D4BHandlerResult
{
    case Completed;
    case Rejected;
    case Retryable;
    case Throws;
}

/** @mago-expect lint:single-class-per-file */
final class D4BRuntimeHandler implements OperationHandler
{
    public int $calls = 0;

    public ?string $receivedValue = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly D4BHandlerResult $result,
        private readonly ?Closure $onHandle = null,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;
        $value = $operation->value();
        $this->receivedValue = $value instanceof D4BRuntimeValue ? $value->value : null;
        if ($this->onHandle !== null) {
            ($this->onHandle)();
        }
        $this->connection->executeStatement('INSERT INTO "blackops_authorization_audit".business_updates (value) VALUES (:value)', [
            'value' => 'handler',
        ]);
        if ($this->result === D4BHandlerResult::Rejected) {
            return OperationResult::rejected(RejectionReason::businessRule('business.rejected'));
        }
        if ($this->result === D4BHandlerResult::Retryable && $this->calls === 1) {
            throw new D4BRetryableException('temporary handler failure');
        }
        if ($this->result === D4BHandlerResult::Throws) {
            throw new RuntimeException('business handler failure');
        }

        return OperationResult::completed(new EmptyOutcome());
    }
}

/** @mago-expect lint:single-class-per-file */
final class D4BRetryableException extends RuntimeException implements RetryableException {}

/** @mago-expect lint:single-class-per-file */
final class D4BPolicy implements AuthorizationPolicy
{
    public int $calls = 0;

    public ?string $lastRequestValue = null;

    public ?string $lastRejectionCode = null;

    public ?Closure $onDecision = null;

    /** @var list<AuthorizationDecision> */
    private array $decisions;

    public function __construct(AuthorizationDecision ...$decisions)
    {
        $this->decisions = $decisions;
    }

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        ++$this->calls;
        $value = $request->value();
        $this->lastRequestValue = $value instanceof D4BRuntimeValue ? $value->value : null;
        if ($this->onDecision !== null) {
            ($this->onDecision)($this->calls);
        }

        $decision = array_shift($this->decisions) ?? AuthorizationDecision::allow();
        $this->lastRejectionCode = $decision->code();

        return $decision;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D4BContainer implements ContainerInterface
{
    public function __construct(
        private ?object $service = null,
        private ?AuthorizationPolicy $policy = null,
    ) {}

    public function get(string $id): mixed
    {
        if ($this->policy !== null && $id === $this->policy::class) {
            return $this->policy;
        }
        if ($this->service !== null && $id === $this->service::class) {
            return $this->service;
        }
        throw new RuntimeException('D4B test service is unavailable.');
    }

    public function has(string $id): bool
    {
        return (
            $this->policy !== null
            && $id === $this->policy::class
            || $this->service !== null
            && $id === $this->service::class
        );
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D4BClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-16T00:00:10.000000Z');
    }
}

/** @mago-expect lint:single-class-per-file */
final class D4BPublicUuidv7Generator implements Uuidv7Generator
{
    private static int $next = 1;

    public function generate(): string
    {
        return '019f9d20-0001-7000-8000-' . str_pad(dechex(self::$next++), 12, '0', STR_PAD_LEFT);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D4BStoreUuidv7Generator implements StoreUuidv7Generator
{
    private static int $next = 1;

    public function generate(DateTimeImmutable $time): string
    {
        unset($time);

        return '019f9d20-0002-7000-8000-' . str_pad(dechex(self::$next++), 12, '0', STR_PAD_LEFT);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D4BAfterCommitReporter implements AfterCommitFailureReporter
{
    public function report(AfterCommitFailure $failure): void
    {
        unset($failure);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D4BAuditNonceSource implements NonceSource
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;

        return substr(hash('sha256', 'd4b-audit-nonce-' . $this->counter, true), 0, 24);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D4BAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        unset($tenant);

        return $this->keyFor($purpose);
    }

    public function key(
        string $keyId,
        ?\BlackOps\Core\Audit\AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        unset($tenant);
        $key = $this->keyFor($purpose);
        if ($key->id() !== $keyId) {
            throw new RuntimeException('Unexpected D4B audit key identifier.');
        }

        return $key;
    }

    private function keyFor(AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey(
            $purpose === AuditProtectionPurpose::AuditRecord ? 'd4b-record' : 'd4b-outbox',
            $purpose === AuditProtectionPurpose::AuditRecord ? str_repeat('r', 32) : str_repeat('o', 32),
        );
    }
}

/** @mago-expect lint:single-class-per-file */
final class D4BCommitThenThrowingConnection extends Connection
{
    private bool $throwAfterCommit = false;

    private ?Throwable $failure = null;

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function arm(?Throwable $failure = null): void
    {
        $this->throwAfterCommit = true;
        $this->failure = $failure;
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->throwAfterCommit) {
            $this->throwAfterCommit = false;
            $failure = $this->failure;
            $this->failure = null;
            throw $failure ?? new RuntimeException('commit response lost after durable commit.');
        }
    }

    public function rollBack(): void
    {
        if (!$this->isTransactionActive()) {
            return;
        }

        parent::rollBack();
    }
}
