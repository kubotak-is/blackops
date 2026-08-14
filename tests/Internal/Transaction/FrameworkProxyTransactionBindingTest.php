<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeFixtures.php';

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\OperationResult;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\FrameworkProxyOperationOwnershipGuard;
use BlackOps\Internal\Transaction\FrameworkProxyAfterCommitBinding;
use BlackOps\Internal\Transaction\FrameworkProxyTransactionBinding;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime\FrameworkRuntimeOperation;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime\FrameworkRuntimeService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrameworkProxyTransactionBindingTest extends TestCase
{
    private Connection $app;
    private Connection $analytics;
    private string $table;
    private TransactionRuntimeAccessor $accessor;
    private FrameworkRuntimeFailureReporter $reporter;
    private FrameworkRuntimeDatabaseManager $manager;

    protected function setUp(): void
    {
        $this->app = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $this->analytics = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $this->table = 'blackops_framework_runtime_' . bin2hex(random_bytes(5));
        $this->app->executeStatement(sprintf(
            'CREATE TABLE %s (id INTEGER PRIMARY KEY, value TEXT NOT NULL)',
            $this->app->quoteIdentifier($this->table),
        ));
        $manager = new FrameworkRuntimeDatabaseManager($this->app, $this->analytics);
        $this->manager = $manager;
        $this->reporter = new FrameworkRuntimeFailureReporter();
        $runtime = new TransactionRuntime($manager, $this->reporter, new ExecutionScopeProvider());
        $this->accessor = new TransactionRuntimeAccessor();
        $this->accessor->set($runtime);
    }

    protected function tearDown(): void
    {
        while ($this->app->isTransactionActive()) {
            $this->app->rollBack();
        }
        while ($this->analytics->isTransactionActive()) {
            $this->analytics->rollBack();
        }
        $this->app->executeStatement('DROP TABLE IF EXISTS ' . $this->app->quoteIdentifier($this->table));
        $this->app->close();
        $this->analytics->close();
    }

    public function testServiceTransactionalBindingUsesResolvedConnectionAndReturnsResult(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $service = new FrameworkRuntimeService();

        $result = $binding->invoke($service, 'run', ['value'], static fn(): string => 'result', 'app');

        self::assertSame('result', $result);
        self::assertSame(['app'], $this->manager->requested);
        self::assertNotSame($this->app, $this->analytics);
        self::assertFalse($this->app->isTransactionActive());
    }

    public function testOperationBindingPassesThroughLifecycleExactlyOnce(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->operationBinding());
        $calls = 0;

        $result = $binding->invoke(
            new FrameworkRuntimeOperation(),
            'handle',
            [],
            function () use (&$calls): string {
                $calls++;
                return $this->accessor->transactional('app', function (): string {
                    self::assertSame(1, $this->app->getTransactionNestingLevel());

                    return 'lifecycle-owned';
                });
            },
            'app',
        );

        self::assertSame('lifecycle-owned', $result);
        self::assertSame(1, $calls);
        self::assertFalse($this->app->isTransactionActive());
    }

    #[DataProvider('lifecycleStrategies')]
    public function testInlineDeferredAndSelfHandledLifecycleEachPassThroughOnce(
        string $strategy,
        bool $selfHandled,
    ): void {
        $binding = $this->operationBinding();
        $metadata = new \BlackOps\Core\Registry\OperationMetadata(
            'operation.runtime',
            FrameworkRuntimeOperation::class,
            'Value',
            'Handler',
            'Outcome',
            $strategy,
            typedSelfHandled: $selfHandled,
            transactionConnection: 'app',
        );
        new FrameworkProxyOperationOwnershipGuard()->assertOperationMetadata($binding, $metadata);
        $calls = 0;

        $binding = new FrameworkProxyTransactionBinding($this->accessor, $binding);
        $binding->invoke(
            new FrameworkRuntimeOperation(),
            'handle',
            [],
            static function () use (&$calls): void {
                $calls++;
            },
            'app',
        );

        self::assertSame(1, $calls);
        self::assertFalse($this->app->isTransactionActive());
    }

    /** @return iterable<string,array{string,bool}> */
    public static function lifecycleStrategies(): iterable
    {
        yield 'inline' => [Inline::class, false];
        yield 'deferred' => [\BlackOps\Core\Execution\Deferred::class, false];
        yield 'self handled' => [Inline::class, true];
    }

    public function testNestedFailureMarksRequiredOuterScopeRollbackOnly(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());

        try {
            $binding->invoke(
                new FrameworkRuntimeService(),
                'run',
                [],
                function () use ($binding): void {
                    try {
                        $binding->invoke(
                            new FrameworkRuntimeService(),
                            'run',
                            [],
                            static function (): never {
                                throw new RuntimeException('inner failure');
                            },
                            'app',
                        );
                    } catch (RuntimeException) {
                    }
                },
                'app',
            );
            self::fail('Expected outer failure.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('rollback-only', $exception->getMessage());
        }

        self::assertFalse($this->app->isTransactionActive());
    }

    public function testManualTransactionCollisionDoesNotInvokeService(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $called = false;
        $this->app->beginTransaction();

        try {
            $binding->invoke(
                new FrameworkRuntimeService(),
                'run',
                [],
                static function () use (&$called): void {
                    $called = true;
                },
                'app',
            );
            self::fail('Expected manual transaction collision.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('manual transaction', $exception->getMessage());
        } finally {
            $this->app->rollBack();
        }

        self::assertFalse($called);
    }

    public function testManualTransactionLeakIsRolledBackAndCallbackDiscarded(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $called = false;

        try {
            $binding->invoke(
                new FrameworkRuntimeService(),
                'run',
                [],
                function () use (&$called): void {
                    $this->insert($this->app, 9, 'leaked');
                    $this->app->beginTransaction();
                    $called = true;
                },
                'app',
            );
            self::fail('Expected transaction nesting leak.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('nesting level', $exception->getMessage());
        }

        self::assertTrue($called);
        self::assertSame([], $this->values());
        self::assertFalse($this->app->isTransactionActive());
    }

    public function testServiceThrowableRollsBackAndPreservesThrowableIdentity(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $failure = new RuntimeException('primary failure');

        try {
            $binding->invoke(
                new FrameworkRuntimeService(),
                'run',
                [],
                static function () use ($failure): never {
                    throw $failure;
                },
                'app',
            );
            self::fail('Expected service failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertFalse($this->app->isTransactionActive());
    }

    public function testAfterCommitRegistrationOrderRollbackDiscardAndFailureIsolation(): void
    {
        $binding = new FrameworkProxyAfterCommitBinding($this->accessor, $this->serviceBinding());
        $events = [];
        $this->accessor->transactional('app', function () use ($binding, &$events): void {
            $binding->invoke(new FrameworkRuntimeService(), 'notify', ['first'], static function () use (
                &$events,
            ): void {
                $events[] = 'first';
            });
            $binding->invoke(new FrameworkRuntimeService(), 'notify', ['failure'], static function (): never {
                throw new RuntimeException('callback failure');
            });
            $binding->invoke(new FrameworkRuntimeService(), 'notify', ['second'], static function () use (
                &$events,
            ): void {
                $events[] = 'second';
            });
        });

        self::assertSame(['first', 'second'], $events);
        self::assertCount(1, $this->reporter->failures);

        try {
            $this->accessor->transactional('app', function () use ($binding, &$events): never {
                $binding->invoke(new FrameworkRuntimeService(), 'notify', ['discarded'], static function () use (
                    &$events,
                ): void {
                    $events[] = 'discarded';
                });
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        self::assertSame(['first', 'second'], $events);
    }

    public function testOperationTerminalAndOutcomeShareLifecycleTransactionWhileServiceOnlyGuaranteesMethod(): void
    {
        $operationMetadata = new \BlackOps\Core\Registry\OperationMetadata(
            'operation.runtime',
            FrameworkRuntimeOperation::class,
            'Value',
            'Handler',
            'Outcome',
            Inline::class,
            transactionConnection: 'app',
        );
        $manager = new class($this->app) implements DatabaseManager {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function connection(?string $name = null): Connection
            {
                return $this->connection;
            }
        };
        $runtime = new TransactionRuntime($manager, $this->reporter, new ExecutionScopeProvider());
        $coordinator = new OperationTransactionCoordinator($runtime, $manager, $this->app);
        $coordinator->execute(
            $operationMetadata,
            function (): OperationResult {
                $this->insert($this->app, 1, 'business');
                return OperationResult::completed();
            },
            function (): void {
                $this->insert($this->app, 2, 'terminal');
            },
        );
        self::assertSame(['business', 'terminal'], $this->values());

        try {
            $coordinator->execute(
                $operationMetadata,
                function (): OperationResult {
                    $this->insert($this->app, 4, 'failed-business');
                    return OperationResult::completed();
                },
                function (): void {
                    $this->insert($this->app, 5, 'failed-terminal');
                    throw new RuntimeException('terminal persistence failure');
                },
            );
            self::fail('Expected terminal failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('terminal persistence failure', $exception->getMessage());
        }
        self::assertSame(['business', 'terminal'], $this->values());

        $service = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $service->invoke(
            new FrameworkRuntimeService(),
            'run',
            [],
            function (): void {
                $this->insert($this->app, 3, 'service');
            },
            'app',
        );
        try {
            throw new RuntimeException('later terminal failure');
        } catch (RuntimeException $exception) {
            self::assertSame('later terminal failure', $exception->getMessage());
        }
        self::assertSame(['business', 'terminal', 'service'], $this->values());
    }

    public function testAfterCommitQueuesReceiverArgumentsAndRunsAfterCommit(): void
    {
        $binding = new FrameworkProxyAfterCommitBinding($this->accessor, $this->serviceBinding());
        $service = new FrameworkRuntimeService();
        $events = [];

        $this->accessor->transactional('app', function () use ($binding, $service, &$events): void {
            $binding->invoke($service, 'notify', ['value'], static function () use (&$events): void {
                $events[] = 'notify:value';
            });
            self::assertSame([], $events);
        });

        self::assertSame(['notify:value'], $events);
    }

    public function testAfterCommitRunsImmediatelyOutsideTransactionAndContinuesAfterFailure(): void
    {
        $binding = new FrameworkProxyAfterCommitBinding($this->accessor, $this->serviceBinding());
        $events = [];

        $binding->invoke(new FrameworkRuntimeService(), 'notify', ['immediate'], static function () use (
            &$events,
        ): void {
            $events[] = 'immediate';
        });
        self::assertSame(['immediate'], $events);

        $this->accessor->transactional('app', function () use ($binding, &$events): void {
            $binding->invoke(new FrameworkRuntimeService(), 'notify', ['failure'], static function (): never {
                throw new RuntimeException('callback failure');
            });
            $binding->invoke(new FrameworkRuntimeService(), 'notify', ['success'], static function () use (
                &$events,
            ): void {
                $events[] = 'success';
            });
        });

        self::assertSame(['immediate', 'success'], $events);
    }

    public function testOperationAfterCommitUsesCurrentTransactionScope(): void
    {
        $binding = new FrameworkProxyAfterCommitBinding($this->accessor, $this->operationBinding());
        $called = false;

        $this->accessor->transactional('app', function () use ($binding, &$called): void {
            $binding->invoke(new FrameworkRuntimeOperation(), 'callback', [], static function () use (&$called): void {
                $called = true;
            });
            self::assertFalse($called);
        });

        self::assertTrue($called);
    }

    public function testTamperedTransactionalConnectionIsRejectedBeforeProceed(): void
    {
        $binding = new FrameworkProxyTransactionBinding($this->accessor, $this->serviceBinding());
        $called = false;

        try {
            $binding->invoke(
                new FrameworkRuntimeService(),
                'run',
                [],
                static function () use (&$called): void {
                    $called = true;
                },
                'analytics',
            );
            self::fail('Expected connection metadata mismatch.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('BO_PROXY_OWNERSHIP_CONFLICT', $exception->getMessage());
        }

        self::assertFalse($called);
    }

    private function serviceBinding(): FrameworkProxyDefinitionBinding
    {
        return $this->binding(FrameworkRuntimeService::class, 'service');
    }

    private function operationBinding(): FrameworkProxyDefinitionBinding
    {
        return $this->binding(FrameworkRuntimeOperation::class, 'operation');
    }

    private function binding(string $class, string $id): FrameworkProxyDefinitionBinding
    {
        $metadata = new FrameworkProxyContract()->inspect(
            $class,
            FrameworkProxyProfile::FRAMEWORK,
            $id,
            'runtime-test',
            'app',
            ['app'],
        );

        return new FrameworkProxyDefinitionBinding($id, $class, $class, $metadata, $metadata->marker());
    }

    private function insert(Connection $connection, int $id, string $value): void
    {
        $connection->insert($this->table, ['id' => $id, 'value' => $value]);
    }

    /** @return list<string> */
    private function values(): array
    {
        /** @var list<string> $values */
        $values = $this->app->fetchFirstColumn(
            'SELECT value FROM ' . $this->app->quoteIdentifier($this->table) . ' ORDER BY id',
        );

        return $values;
    }
}

final class FrameworkRuntimeFailureReporter implements AfterCommitFailureReporter
{
    /** @var list<AfterCommitFailure> */
    public array $failures = [];

    public function report(AfterCommitFailure $failure): void
    {
        $this->failures[] = $failure;
    }
}

final class FrameworkRuntimeDatabaseManager implements DatabaseManager
{
    /** @var list<?string> */
    public array $requested = [];

    public function __construct(
        private readonly Connection $app,
        private readonly Connection $analytics,
    ) {}

    public function connection(?string $name = null): Connection
    {
        $this->requested[] = $name;

        return match ($name) {
            null, 'app' => $this->app,
            'analytics' => $this->analytics,
            default => throw new RuntimeException('Unknown test connection.'),
        };
    }
}
