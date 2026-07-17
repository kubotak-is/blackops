<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\DefaultAfterCommitFailureReporter;
use BlackOps\Internal\Transaction\TransactionRuntime;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class TransactionRuntimeTest extends TestCase
{
    private Connection $app;
    private Connection $analytics;
    private string $table;
    private ExecutionScopeProvider $executionScope;

    protected function setUp(): void
    {
        $this->app = DriverManager::getConnection($this->connectionParameters());
        $this->analytics = DriverManager::getConnection($this->connectionParameters());
        $this->table = 'blackops_transaction_' . bin2hex(random_bytes(6));
        $this->executionScope = new ExecutionScopeProvider();
        $this->app->executeStatement(sprintf(
            'CREATE TABLE %s (id INTEGER PRIMARY KEY, value TEXT NOT NULL)',
            $this->app->quoteIdentifier($this->table),
        ));
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

    public function testRootScopeCommitsReturnAndRollsBackThrowable(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());

        $result = $runtime->transactional('app', function (): string {
            $this->insert($this->app, 1, 'committed');

            return 'result';
        });

        self::assertSame('result', $result);
        self::assertSame(['committed'], $this->values());

        try {
            $runtime->transactional('app', function (): never {
                $this->insert($this->app, 2, 'rolled-back');

                throw new RuntimeException('business failure');
            });
            self::fail('Expected business failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('business failure', $exception->getMessage());
        }

        self::assertSame(['committed'], $this->values());
        self::assertFalse($this->app->isTransactionActive());
    }

    public function testSameConnectionNestedScopeUsesOneTransactionAndFailureMarksRollbackOnly(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());
        $levels = [];

        try {
            $runtime->transactional('app', function () use ($runtime, &$levels): void {
                $levels[] = $this->app->getTransactionNestingLevel();
                $this->insert($this->app, 1, 'outer');

                try {
                    $runtime->transactional('app', function () use (&$levels): never {
                        $levels[] = $this->app->getTransactionNestingLevel();
                        $this->insert($this->app, 2, 'inner');

                        throw new RuntimeException('inner failure');
                    });
                } catch (RuntimeException) {
                }

                $levels[] = $this->app->getTransactionNestingLevel();
            });
            self::fail('Expected rollback-only failure.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('rollback-only', $exception->getMessage());
        }

        self::assertSame([1, 1, 1], $levels);
        self::assertSame([], $this->values());
        self::assertFalse($this->app->isTransactionActive());
    }

    public function testDifferentConnectionsCommitIndependently(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());
        $callbacks = [];

        try {
            $runtime->transactional('app', function () use ($runtime, &$callbacks): never {
                $this->insert($this->app, 1, 'outer');
                $runtime->afterCommit(self::class, 'outer', static function () use (&$callbacks): void {
                    $callbacks[] = 'outer';
                });
                $runtime->transactional('analytics', function () use ($runtime, &$callbacks): void {
                    $this->insert($this->analytics, 2, 'inner');
                    $runtime->afterCommit(self::class, 'inner', static function () use (&$callbacks): void {
                        $callbacks[] = 'inner';
                    });
                });

                throw new RuntimeException('outer failure');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('outer failure', $exception->getMessage());
        }

        self::assertSame(['inner'], $callbacks);
        self::assertSame(['inner'], $this->values());
    }

    public function testReentrantConnectionQueuesAfterCommitOnLogicalInvocationScope(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());
        $callbacks = [];

        try {
            $runtime->transactional('app', function () use ($runtime, &$callbacks): never {
                $this->insert($this->app, 1, 'outer-rollback');
                $runtime->transactional('analytics', function () use ($runtime, &$callbacks): void {
                    $this->insert($this->analytics, 2, 'analytics-commit');
                    $runtime->afterCommit(self::class, 'analytics', static function () use (&$callbacks): void {
                        $callbacks[] = 'analytics';
                    });
                    $runtime->transactional('app', function () use ($runtime, &$callbacks): void {
                        $runtime->afterCommit(self::class, 'appReentry', static function () use (&$callbacks): void {
                            $callbacks[] = 'app-reentry-discarded';
                        });
                    });
                });

                throw new RuntimeException('outer app rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('outer app rollback', $exception->getMessage());
        }

        self::assertSame(['analytics'], $callbacks);
        self::assertSame(['analytics-commit'], $this->values());
        self::assertFalse($this->app->isTransactionActive());
        self::assertFalse($this->analytics->isTransactionActive());

        $runtime->transactional('app', function () use ($runtime, &$callbacks): void {
            $this->insert($this->app, 3, 'app-commit');
            $runtime->afterCommit(self::class, 'app', static function () use (&$callbacks): void {
                $callbacks[] = 'app';
            });

            try {
                $runtime->transactional('analytics', function () use ($runtime, &$callbacks): never {
                    $this->insert($this->analytics, 4, 'analytics-rollback');
                    $runtime->afterCommit(self::class, 'analyticsRollback', static function () use (&$callbacks): void {
                        $callbacks[] = 'analytics-discarded';
                    });
                    $runtime->transactional('app', function () use ($runtime, &$callbacks): void {
                        $runtime->afterCommit(self::class, 'appReentryCommit', static function () use (
                            &$callbacks,
                        ): void {
                            $callbacks[] = 'app-reentry';
                        });
                    });

                    throw new RuntimeException('analytics rollback');
                });
            } catch (RuntimeException $exception) {
                self::assertSame('analytics rollback', $exception->getMessage());
            }
        });

        self::assertSame(['analytics', 'app', 'app-reentry'], $callbacks);
        self::assertSame(['analytics-commit', 'app-commit'], $this->values());
        self::assertFalse($this->app->isTransactionActive());
        self::assertFalse($this->analytics->isTransactionActive());
    }

    public function testManualTransactionCollisionFailsBeforeMethodAndLeakIsCleaned(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());
        $called = false;
        $this->app->beginTransaction();

        try {
            $runtime->transactional('app', static function () use (&$called): void {
                $called = true;
            });
            self::fail('Expected manual transaction collision.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('manual transaction', $exception->getMessage());
        } finally {
            $this->app->rollBack();
        }

        self::assertFalse($called);

        $callbackCalled = false;

        try {
            $runtime->transactional('app', function () use ($runtime, &$callbackCalled): void {
                $this->insert($this->app, 1, 'leaked');
                $runtime->afterCommit(self::class, 'leaked', static function () use (&$callbackCalled): void {
                    $callbackCalled = true;
                });
                $this->app->beginTransaction();
            });
            self::fail('Expected manual transaction nesting leak.');
        } catch (TransactionException $exception) {
            self::assertStringContainsString('nesting level', $exception->getMessage());
        }

        self::assertFalse($callbackCalled);
        self::assertFalse($this->app->isTransactionActive());
        self::assertSame([], $this->values());
    }

    public function testAfterCommitRunsInRegistrationOrderAndIsDiscardedOnRollback(): void
    {
        $runtime = $this->runtime(new RecordingFailureReporter());
        $values = [];
        $runtime->transactional('app', function () use ($runtime, &$values): void {
            $runtime->afterCommit(self::class, 'first', static function () use (&$values): void {
                $values[] = 'first';
            });
            $runtime->transactional('app', function () use ($runtime, &$values): void {
                $runtime->afterCommit(self::class, 'second', static function () use (&$values): void {
                    $values[] = 'second';
                });
            });
            self::assertSame([], $values);
        });

        self::assertSame(['first', 'second'], $values);

        try {
            $runtime->transactional('app', function () use ($runtime, &$values): never {
                $runtime->afterCommit(self::class, 'discarded', static function () use (&$values): void {
                    $values[] = 'discarded';
                });

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $runtime->afterCommit(self::class, 'immediate', static function () use (&$values): void {
            $values[] = 'immediate';
        });

        self::assertSame(['first', 'second', 'immediate'], $values);
    }

    public function testCallbackAndReporterFailuresDoNotStopLaterCallbacks(): void
    {
        $reporter = new RecordingFailureReporter(throwAfterReport: true);
        $runtime = $this->runtime($reporter);
        $values = [];

        $runtime->transactional('app', function () use ($runtime, &$values): void {
            $runtime->afterCommit(self::class, 'firstFailure', static function (): never {
                throw new RuntimeException('first sensitive failure');
            });
            $runtime->afterCommit(self::class, 'firstSuccess', static function () use (&$values): void {
                $values[] = 'first-success';
            });
            $runtime->afterCommit(self::class, 'secondFailure', static function (): never {
                throw new RuntimeException('second sensitive failure');
            });
            $runtime->afterCommit(self::class, 'secondSuccess', static function () use (&$values): void {
                $values[] = 'second-success';
            });
        });

        self::assertSame(['first-success', 'second-success'], $values);
        self::assertCount(2, $reporter->failures);
        self::assertSame(
            ['firstFailure', 'secondFailure'],
            array_map(static fn(AfterCommitFailure $failure): string => $failure->method(), $reporter->failures),
        );
    }

    public function testFailureCapturesRegistrationContextAndDefaultLogOmitsSensitiveDetails(): void
    {
        $reporter = new RecordingFailureReporter();
        $runtime = $this->runtime($reporter);
        $envelope = $this->envelope();

        $this->executionScope->run($envelope, function () use ($runtime): void {
            $runtime->transactional('app', function () use ($runtime): void {
                $runtime->afterCommit('App\\CredentialMailer', 'send', static function (): never {
                    throw new RuntimeException('secret callback argument and database-password');
                });
            });
        });

        self::assertCount(1, $reporter->failures);
        $failure = $reporter->failures[0];
        self::assertSame($envelope->context(), $failure->context());

        $logger = new RecordingLogger();
        new DefaultAfterCommitFailureReporter($logger)->report($failure);
        $encoded = json_encode($logger->records, JSON_THROW_ON_ERROR);

        self::assertStringContainsString('019f32ab-2be0-7b38-a0a7-1ab2f9687701', $encoded);
        self::assertStringContainsString('019f32ab-2be0-7b38-a0a7-1ab2f9687704', $encoded);
        self::assertStringNotContainsString('secret callback argument', $encoded);
        self::assertStringNotContainsString('database-password', $encoded);
        self::assertStringNotContainsString('RuntimeException', $encoded);
    }

    private function runtime(AfterCommitFailureReporter $reporter): TransactionRuntime
    {
        $manager = new class($this->app, $this->analytics) implements DatabaseManager {
            public function __construct(
                private readonly Connection $app,
                private readonly Connection $analytics,
            ) {}

            public function connection(?string $name = null): Connection
            {
                return match ($name) {
                    null, 'app' => $this->app,
                    'analytics' => $this->analytics,
                    default => throw new RuntimeException('Unknown test connection.'),
                };
            }
        };

        return new TransactionRuntime($manager, $reporter, $this->executionScope);
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

    private function envelope(): OperationEnvelope
    {
        $context = new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701'),
            new DateTimeImmutable('2026-07-18T00:00:00Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687702'),
            CausationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687703'),
            new AttemptContext(
                AttemptId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687704'),
                1,
                new DateTimeImmutable('2026-07-18T00:00:01Z'),
            ),
        );

        return new OperationEnvelope(
            new TransactionTestOperation(),
            new TransactionTestValue(),
            $context,
            new Inline(),
        );
    }
}

final class RecordingFailureReporter implements AfterCommitFailureReporter
{
    /** @var list<AfterCommitFailure> */
    public array $failures = [];

    public function __construct(
        private readonly bool $throwAfterReport = false,
    ) {}

    public function report(AfterCommitFailure $failure): void
    {
        $this->failures[] = $failure;

        if ($this->throwAfterReport) {
            throw new RuntimeException('reporter unavailable');
        }
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}

final readonly class TransactionTestOperation implements Operation {}

final readonly class TransactionTestValue implements OperationValue {}
