<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\TransactionRuntime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperationTransactionCoordinatorTest extends TestCase
{
    private Connection $app;
    private Connection $analytics;
    private string $table;
    private OperationTransactionCoordinator $coordinator;
    private TransactionRuntime $runtime;

    protected function setUp(): void
    {
        $this->app = DriverManager::getConnection($this->connectionParameters());
        $this->analytics = DriverManager::getConnection($this->connectionParameters());
        $this->table = 'blackops_operation_transaction_' . bin2hex(random_bytes(6));
        $this->app->executeStatement(sprintf(
            'CREATE TABLE %s (id INTEGER PRIMARY KEY, value TEXT NOT NULL)',
            $this->app->quoteIdentifier($this->table),
        ));
        $manager = new OperationTransactionDatabaseManager($this->app, $this->analytics);
        $this->runtime = new TransactionRuntime(
            $manager,
            new IgnoringOperationTransactionReporter(),
            new ExecutionScopeProvider(),
        );
        $this->coordinator = new OperationTransactionCoordinator($this->runtime, $manager, $this->app);
    }

    protected function tearDown(): void
    {
        foreach ([$this->app, $this->analytics] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->app->executeStatement('DROP TABLE IF EXISTS ' . $this->app->quoteIdentifier($this->table));
        $this->app->close();
        $this->analytics->close();
    }

    public function testSharedConnectionCommitsBusinessAndTerminalBeforeAfterCommit(): void
    {
        $events = [];
        $result = $this->coordinator->execute(
            $this->metadata('app'),
            function () use (&$events): OperationResult {
                self::assertTrue($this->app->isTransactionActive());
                $this->insert($this->app, 1, 'business');
                $this->runtime->afterCommit(self::class, 'callback', function () use (&$events): void {
                    $events[] = $this->values();
                });

                return OperationResult::completed();
            },
            function () use (&$events): void {
                self::assertTrue($this->app->isTransactionActive());
                $this->insert($this->app, 2, 'terminal');
                $events[] = ['terminal-written'];
            },
        );

        self::assertTrue($result->isCompleted());
        self::assertSame([['terminal-written'], ['business', 'terminal']], $events);
        self::assertSame(['business', 'terminal'], $this->values());
    }

    public function testRejectionAndSharedTerminalFailureRollbackBusinessUpdate(): void
    {
        $terminalCalled = false;
        $rejected = $this->coordinator->execute(
            $this->metadata('app'),
            function (): OperationResult {
                $this->insert($this->app, 1, 'rejected');

                return OperationResult::rejected(RejectionReason::businessRule('order.rejected'));
            },
            static function () use (&$terminalCalled): void {
                $terminalCalled = true;
            },
        );

        self::assertTrue($rejected->isRejected());
        self::assertFalse($terminalCalled);
        self::assertSame([], $this->values());

        try {
            $this->coordinator->execute(
                $this->metadata('app'),
                function (): OperationResult {
                    $this->insert($this->app, 2, 'terminal-failure');

                    return OperationResult::completed();
                },
                static function (): never {
                    throw new RuntimeException('terminal failure');
                },
            );
            self::fail('Expected terminal failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('terminal failure', $exception->getMessage());
        }

        self::assertSame([], $this->values());
    }

    public function testDifferentConnectionCommitsApplicationBeforeFrameworkTerminal(): void
    {
        $terminalCalled = false;
        $result = $this->coordinator->execute(
            $this->metadata('analytics'),
            function (): OperationResult {
                $this->insert($this->analytics, 1, 'application-committed');

                return OperationResult::completed();
            },
            static function () use (&$terminalCalled): void {
                $terminalCalled = true;
            },
        );

        self::assertTrue($result->isCompleted());
        self::assertFalse($terminalCalled);
        self::assertSame(['application-committed'], $this->values());
    }

    public function testConnectionNameAloneDoesNotSelectSharedFrameworkTransaction(): void
    {
        $manager = new OperationTransactionDatabaseManager($this->analytics, $this->app);
        $runtime = new TransactionRuntime(
            $manager,
            new IgnoringOperationTransactionReporter(),
            new ExecutionScopeProvider(),
        );
        $coordinator = new OperationTransactionCoordinator($runtime, $manager, $this->app);

        self::assertFalse($coordinator->sharesFrameworkConnection($this->metadata('app')));
    }

    public function testSameConnectionNestedFailureMarksOperationRootRollbackOnly(): void
    {
        $terminalCalled = false;

        try {
            $this->coordinator->execute(
                $this->metadata('app'),
                function (): OperationResult {
                    $this->insert($this->app, 1, 'outer');

                    try {
                        $this->runtime->transactional('app', function (): never {
                            $this->insert($this->app, 2, 'inner');

                            throw new RuntimeException('inner failure');
                        });
                    } catch (RuntimeException) {
                    }

                    return OperationResult::completed();
                },
                function () use (&$terminalCalled): void {
                    $terminalCalled = true;
                    $this->insert($this->app, 3, 'terminal');
                },
            );
            self::fail('Expected rollback-only operation transaction.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('rollback-only', $exception->getMessage());
        }

        self::assertTrue($terminalCalled);
        self::assertSame([], $this->values());
    }

    private function metadata(string $connection): OperationMetadata
    {
        return new OperationMetadata(
            'transaction.test',
            OperationTransactionDefinition::class,
            OperationTransactionValue::class,
            OperationTransactionDefinition::class,
            EmptyOutcome::class,
            Inline::class,
            transactionConnection: $connection,
        );
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

    /** @return array<string, mixed> */
    private function connectionParameters(): array
    {
        return [
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ];
    }
}

final readonly class OperationTransactionDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $app,
        private Connection $analytics,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return match ($name) {
            null, 'app' => $this->app,
            'analytics' => $this->analytics,
            default => throw new RuntimeException('Unknown test connection.'),
        };
    }
}

final readonly class IgnoringOperationTransactionReporter implements AfterCommitFailureReporter
{
    public function report(AfterCommitFailure $failure): void {}
}

final readonly class OperationTransactionDefinition implements Operation {}

final readonly class OperationTransactionValue implements OperationValue {}
