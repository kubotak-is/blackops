<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\TransactionRuntime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionRuntimeFailureTest extends TestCase
{
    public function testCommitFailureDiscardsQueueAndRuntimeStateBeforeNextInvocation(): void
    {
        $active = false;
        $level = 0;
        $commits = 0;
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$active): bool {
                return $active;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$level): int {
                return $level;
            });
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = true;
                $level = 1;
            });
        $connection
            ->method('commit')
            ->willReturnCallback(static function () use (&$active, &$level, &$commits): void {
                $commits++;

                if ($commits === 1) {
                    throw new RuntimeException('database-password must stay in the previous throwable');
                }

                $active = false;
                $level = 0;
            });
        $connection
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });
        $manager = new class($connection) implements DatabaseManager {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function connection(?string $name = null): Connection
            {
                return $this->connection;
            }
        };
        $reporter = new class implements AfterCommitFailureReporter {
            public function report(AfterCommitFailure $failure): void {}
        };
        $runtime = new TransactionRuntime($manager, $reporter, new ExecutionScopeProvider());
        $callbackCalled = false;

        try {
            $runtime->transactional('app', function () use ($runtime, &$callbackCalled): void {
                $runtime->afterCommit(self::class, 'callback', static function () use (&$callbackCalled): void {
                    $callbackCalled = true;
                });
            });
            self::fail('Expected commit failure.');
        } catch (TransactionException $exception) {
            self::assertSame('Database transaction commit failed.', $exception->getMessage());
            self::assertStringNotContainsString('database-password', $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }

        self::assertFalse($callbackCalled);
        self::assertSame('next', $runtime->transactional('app', static fn(): string => 'next'));
        self::assertSame(2, $commits);
    }
}
