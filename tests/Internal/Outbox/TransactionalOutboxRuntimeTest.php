<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Outbox;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Codec\EncodedOperationMessage;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Database\DatabaseManager;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Outbox\TransactionalOutboxRuntime;
use BlackOps\Internal\Transaction\DefaultAfterCommitFailureReporter;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TransactionalOutboxRuntimeTest extends TestCase
{
    private Connection $app;
    private Connection $other;
    private TransactionalOutboxRuntime $outbox;
    private TransactionRuntime $transactionRuntime;
    private ExecutionScopeProvider $scope;
    private ContextCapture $captured;

    protected function setUp(): void
    {
        $this->app = DriverManager::getConnection($this->parameters());
        $this->other = DriverManager::getConnection($this->parameters());
        $this->app->executeStatement('DROP SCHEMA IF EXISTS outbox_runtime_test CASCADE');
        new PostgreSqlOutboxStore($this->app, 'outbox_runtime_test')->migrate();
        $this->app->executeStatement('CREATE TABLE "outbox_runtime_test"."mutations" (id integer PRIMARY KEY)');
        $this->scope = new ExecutionScopeProvider();
        $databases = new FixtureDatabaseManager(['app' => $this->app, 'other' => $this->other]);
        $transactions = new TransactionRuntime($databases, new DefaultAfterCommitFailureReporter(), $this->scope);
        $this->transactionRuntime = $transactions;
        $this->captured = new ContextCapture();
        $clock = new FixtureClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $metadata = new OperationMetadata(
            'fixture.outbox.child',
            OutboxChild::class,
            OutboxChildValue::class,
            OutboxChild::class,
            OutboxChildOutcome::class,
            Deferred::class,
        );
        $inlineMetadata = new OperationMetadata(
            'fixture.outbox.inline',
            InlineChild::class,
            InlineChildValue::class,
            InlineChild::class,
            OutboxChildOutcome::class,
            Inline::class,
        );
        $this->outbox = new TransactionalOutboxRuntime(
            new OperationRegistry([$metadata, $inlineMetadata]),
            new FixtureCodec($this->captured),
            $this->scope,
            $transactions,
            $this->app,
            'app',
            new PostgreSqlOutboxStore($this->app, 'outbox_runtime_test'),
            new ExecutionContextFactory($identifiers, $clock),
            $identifiers,
            $clock,
        );
    }

    protected function tearDown(): void
    {
        while ($this->app->isTransactionActive()) {
            $this->app->rollBack();
        }
        while ($this->other->isTransactionActive()) {
            $this->other->rollBack();
        }
        $this->app->executeStatement('DROP SCHEMA IF EXISTS outbox_runtime_test CASCADE');
        $this->app->close();
        $this->other->close();
    }

    public function testCommitPersistsRegistrationAndBuildsParentChildContext(): void
    {
        $parent = $this->parent();
        $registration = null;
        $runtime = $this->transactionRuntime;

        $runtime->transactional('app', function () use ($parent, &$registration): void {
            $this->scope->run($parent, function () use (&$registration): void {
                $this->app->insert('outbox_runtime_test.mutations', ['id' => 1]);
                $registration = $this->outbox->register(
                    new OutboxChild(),
                    new OutboxChildValue(),
                    new DateTimeImmutable('2026-07-24T01:10:00+00:00'),
                    new ActorRef('override-worker', 'worker'),
                );
            });
        });

        self::assertNotNull($registration);
        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."mutations"'));
        $row = $this->app->fetchAssociative('SELECT * FROM "outbox_runtime_test"."outbox_records"');
        self::assertSame($registration->recordId()->toString(), $row['record_id']);
        self::assertSame($registration->operationId()->toString(), $row['operation_id']);
        self::assertSame(
            '2026-07-24T01:10:00+00:00',
            new DateTimeImmutable($row['available_at'])->format(DateTimeImmutable::ATOM),
        );
        self::assertSame('UTC', $registration->recordedAt()->getTimezone()->getName());
        $child = $this->captured->context;
        self::assertNotNull($child);
        self::assertSame($registration->operationId()->toString(), $child->operationId()->toString());
        self::assertSame($parent->context()->correlationId()->toString(), $child->correlationId()->toString());
        self::assertSame($parent->context()->operationId()->toString(), $child->causationId()?->toString());
        self::assertSame('origin-user', $child->actorContext()?->origin()?->id());
        self::assertSame('auth-user', $child->actorContext()?->authorization()?->id());
        self::assertSame('override-worker', $child->actorContext()?->execution()->id());
        self::assertSame(
            $parent->context()->deadline()?->format(DateTimeImmutable::ATOM),
            $child->deadline()?->format(DateTimeImmutable::ATOM),
        );
        self::assertNull($child->idempotencyKeyHash());
    }

    public function testDispatchDoesNotConstructDefinitionAndReturnsPublicReceipt(): void
    {
        $runtime = new TransactionalOutboxRuntime(
            new OperationRegistry([
                new OperationMetadata(
                    'fixture.outbox.child',
                    NeverConstructedOutboxChild::class,
                    OutboxChildValue::class,
                    NeverConstructedOutboxChild::class,
                    OutboxChildOutcome::class,
                    Deferred::class,
                ),
            ]),
            new FixtureCodec(new ContextCapture()),
            $this->scope,
            $this->transactionRuntime,
            $this->app,
            'app',
            new PostgreSqlOutboxStore($this->app, 'outbox_runtime_test'),
            new ExecutionContextFactory(
                $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock = new FixtureClock()),
                $clock,
            ),
            $identifiers,
            $clock,
        );
        $parent = $this->parent();
        $receipt = null;

        $this->transactionRuntime->transactional('app', function () use ($runtime, $parent, &$receipt): void {
            $this->scope->run($parent, function () use ($runtime, &$receipt): void {
                $receipt = $runtime->dispatch(NeverConstructedOutboxChild::class, new OutboxChildValue());
            });
        });

        self::assertNotNull($receipt);
        self::assertSame('UTC', $receipt->dispatchedAt()->getTimezone()->getName());
        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
    }

    public function testInlineParentCanRegisterDeferredChild(): void
    {
        $this->transactionRuntime->transactional('app', function (): void {
            $this->scope->run($this->parent(new Inline()), function (): void {
                $this->outbox->register(new OutboxChild(), new OutboxChildValue());
            });
        });

        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
    }

    public function testNestedRequiredRegistrationCommitsWithOuterMutation(): void
    {
        $this->transactionRuntime->transactional('app', function (): void {
            $this->scope->run($this->parent(), function (): void {
                $this->app->insert('outbox_runtime_test.mutations', ['id' => 5]);
                $this->transactionRuntime->transactional('app', function (): void {
                    $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                });
            });
        });

        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertSame(1, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."mutations"'));
    }

    public function testInlineChildIsRejectedBeforePersistence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->transactionRuntime->transactional('app', function (): void {
            $this->scope->run($this->parent(), function (): void {
                $this->outbox->register(new InlineChild(), new InlineChildValue());
            });
        });
    }

    public function testRollbackDoesNotLeaveOutboxRow(): void
    {
        $this->expectException(\RuntimeException::class);
        try {
            $this->transactionRuntime->transactional('app', function (): never {
                $this->scope->run($this->parent(), function (): void {
                    $this->app->insert('outbox_runtime_test.mutations', ['id' => 2]);
                    $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                });
                throw new \RuntimeException('rollback');
            });
        } finally {
            self::assertSame(
                0,
                (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'),
            );
            self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."mutations"'));
        }
    }

    public function testNoParentAndManualOrDifferentConnectionFailBeforeInsert(): void
    {
        $this->expectException(\LogicException::class);
        $this->outbox->register(new OutboxChild(), new OutboxChildValue());
    }

    public function testManualTransactionFailsBeforeInsert(): void
    {
        $rejected = false;
        $this->app->beginTransaction();
        try {
            $this->scope->run($this->parent(), function (): void {
                $this->outbox->register(new OutboxChild(), new OutboxChildValue());
            });
            self::fail('Expected manual transaction failure.');
        } catch (\LogicException) {
            $rejected = true;
        } finally {
            $this->app->rollBack();
        }

        self::assertTrue($rejected);
        self::assertNull($this->captured->context);
    }

    public function testOuterAppTransactionAndInnerOtherTransactionFailBeforeInsert(): void
    {
        $this->transactionRuntime->transactional('app', function (): void {
            try {
                $this->transactionRuntime->transactional('other', function (): void {
                    $this->scope->run($this->parent(), function (): void {
                        $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                    });
                });
                self::fail('Expected different top transaction failure.');
            } catch (\LogicException) {
            }
        });

        self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertNull($this->captured->context);
    }

    public function testManualNestedBeginFailsBeforeInsert(): void
    {
        try {
            $this->transactionRuntime->transactional('app', function (): void {
                $this->app->beginTransaction();
                $this->scope->run($this->parent(), function (): void {
                    try {
                        $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                        self::fail('Expected manual nested transaction failure.');
                    } catch (\LogicException) {
                    }
                });
            });
            self::fail('Expected leaked transaction cleanup failure.');
        } catch (\Throwable $failure) {
            self::assertStringContainsString('nesting level', $failure->getMessage());
        }

        self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertNull($this->captured->context);
    }

    public function testManualCommitFailsBeforeAutocommitInsert(): void
    {
        try {
            $this->transactionRuntime->transactional('app', function (): void {
                $this->app->commit();
                $this->scope->run($this->parent(), function (): void {
                    try {
                        $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                        self::fail('Expected manual commit failure.');
                    } catch (\LogicException) {
                    }
                });
            });
            self::fail('Expected committed transaction cleanup failure.');
        } catch (\Throwable $failure) {
            self::assertStringContainsString('nesting level', $failure->getMessage());
        }

        self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertNull($this->captured->context);
    }

    public function testNestedRequiredRollbackOnlyDiscardsOutboxRow(): void
    {
        try {
            $this->transactionRuntime->transactional('app', function (): void {
                $this->scope->run($this->parent(), function (): void {
                    $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                });
                try {
                    $this->transactionRuntime->transactional('app', function (): never {
                        throw new \RuntimeException('inner rejection');
                    });
                } catch (\RuntimeException) {
                }
            });
            self::fail('Expected rollback-only transaction failure.');
        } catch (\Throwable $failure) {
            self::assertStringContainsString('rollback-only', $failure->getMessage());
        }

        self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'));
        self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."mutations"'));
    }

    public function testSameNamedConnectionWithDifferentInstanceFailsBeforeInsert(): void
    {
        $otherRuntime = new TransactionRuntime(
            new FixtureDatabaseManager(['app' => $this->other]),
            new DefaultAfterCommitFailureReporter(),
            $this->scope,
        );
        $outbox = $this->newOutbox($otherRuntime, $this->app);

        try {
            $otherRuntime->transactional('app', function () use ($outbox): void {
                $this->scope->run($this->parent(), function () use ($outbox): void {
                    $outbox->register(new OutboxChild(), new OutboxChildValue());
                });
            });
            self::fail('Expected connection-instance failure.');
        } catch (\LogicException) {
            self::assertSame(
                0,
                (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."outbox_records"'),
            );
        }
    }

    public function testInsertFailureRollsBackAndDoesNotExposeDatabaseDetails(): void
    {
        $this->expectException(DeferredTransportException::class);
        try {
            $this->transactionRuntime->transactional('app', function (): void {
                $this->app->insert('outbox_runtime_test.mutations', ['id' => 4]);
                $this->app->executeStatement('DROP TABLE "outbox_runtime_test"."outbox_records"');
                $this->scope->run($this->parent(), function (): void {
                    $this->outbox->register(new OutboxChild(), new OutboxChildValue());
                });
            });
        } catch (DeferredTransportException $failure) {
            self::assertSame('Failed to persist PostgreSQL outbox record.', $failure->getMessage());
            self::assertStringNotContainsString('outbox_records', $failure->getMessage());
            self::assertSame(0, (int) $this->app->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."mutations"'));
            throw $failure;
        }
    }

    private function newOutbox(TransactionRuntime $transactions, Connection $connection): TransactionalOutboxRuntime
    {
        $clock = new FixtureClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $metadata = new OperationMetadata(
            'fixture.outbox.child',
            OutboxChild::class,
            OutboxChildValue::class,
            OutboxChild::class,
            OutboxChildOutcome::class,
            Deferred::class,
        );
        return new TransactionalOutboxRuntime(
            new OperationRegistry([$metadata]),
            new FixtureCodec(new ContextCapture()),
            $this->scope,
            $transactions,
            $connection,
            'app',
            new PostgreSqlOutboxStore($connection, 'outbox_runtime_test'),
            new ExecutionContextFactory($identifiers, $clock),
            $identifiers,
            $clock,
        );
    }

    private function parent(Deferred|Inline $strategy = new Deferred()): OperationEnvelope
    {
        $clock = new FixtureClock();
        $context = new ExecutionContextFactory(
            new IdentifierFactory(new SymfonyUuidv7Generator(), $clock),
            $clock,
        )->receive(
            new DateTimeImmutable('2026-07-24T02:00:00+00:00'),
            new ActorContext(
                new ActorRef('origin-user', 'user'),
                new ActorRef('auth-user', 'user'),
                new ActorRef('exec-user', 'user'),
            ),
            new IdempotencyKey('parent-key'),
        );

        return new OperationEnvelope(new OutboxParent(), new OutboxParentValue(), $context, $strategy);
    }

    /** @return array<string, mixed> */
    private function parameters(): array
    {
        return [
            'dbname' => 'blackops',
            'user' => 'blackops',
            'password' => 'blackops',
            'host' => 'postgres',
            'driver' => 'pdo_pgsql',
        ];
    }
}

final readonly class FixtureDatabaseManager implements DatabaseManager
{
    /** @param array<string, Connection> $connections */
    public function __construct(
        private array $connections,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return $this->connections[$name ?? 'app'];
    }
}

final readonly class FixtureClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00');
    }
}

final class ContextCapture
{
    public ?ExecutionContext $context = null;
}

final readonly class FixtureCodec implements OperationCodec
{
    public function __construct(
        private ContextCapture $capture,
    ) {}

    public function encode(
        \BlackOps\Core\Registry\OperationMetadata $metadata,
        OperationValue $value,
        ExecutionContext $context,
    ): EncodedOperationMessage {
        $this->capture->context = $context;

        return new EncodedOperationMessage($metadata->typeId, 1, '{"value":"opaque"}', '{"context":"opaque"}');
    }

    public function decodeValue(
        \BlackOps\Core\Registry\OperationMetadata $metadata,
        int $schemaVersion,
        string $encodedPayload,
    ): OperationValue {
        return new OutboxChildValue();
    }

    public function decodeContext(int $schemaVersion, string $encodedContext): ExecutionContext
    {
        return new FixtureClockContextFactory()->context();
    }
}

final readonly class FixtureClockContextFactory
{
    public function context(): ExecutionContext
    {
        $clock = new FixtureClock();
        return new ExecutionContextFactory(
            new IdentifierFactory(new SymfonyUuidv7Generator(), $clock),
            $clock,
        )->receive();
    }
}

final readonly class OutboxParent implements Operation {}

final readonly class OutboxParentValue implements OperationValue {}

final readonly class OutboxChild implements Operation {}

final readonly class NeverConstructedOutboxChild implements Operation
{
    public function __construct()
    {
        throw new \RuntimeException('Dispatch must not construct child definitions.');
    }
}

final readonly class OutboxChildValue implements OperationValue {}

final readonly class OutboxChildOutcome {}

final readonly class InlineChild implements Operation {}

final readonly class InlineChildValue implements OperationValue {}
