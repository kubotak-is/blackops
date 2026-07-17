<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeTransactionServiceInjectorTest extends TestCase
{
    public function testApplicationReporterIsKeptAndUsedByInjectedRuntime(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $reporter = new RuntimeInjectionReporter();
        $builder->set(AfterCommitFailureReporter::class, $reporter);
        $compiler->registerDatabaseServices($builder);
        $container = $compiler->compile($builder);
        $connection = $this->connection();
        $manager = new RuntimeInjectionDatabaseManager($connection);
        $runtime = new RuntimeTransactionServiceInjector()->inject($container, $manager, new ExecutionScopeProvider());

        $runtime->transactional('app', function () use ($runtime): void {
            $runtime->afterCommit(self::class, 'callback', static function (): never {
                throw new RuntimeException('expected callback failure');
            });
        });

        self::assertCount(1, $reporter->failures);
        self::assertSame('callback', $reporter->failures[0]->method());
    }

    public function testMissingTransactionSyntheticDefinitionIsRejected(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $container = $compiler->compile($compiler->builder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('transaction service definition');

        new RuntimeTransactionServiceInjector()->inject(
            $container,
            new RuntimeInjectionDatabaseManager($this->connection()),
            new ExecutionScopeProvider(),
        );
    }

    private function connection(): Connection
    {
        $active = false;
        $level = 0;
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
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });
        $connection
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });

        return $connection;
    }
}

final class RuntimeInjectionDatabaseManager implements DatabaseManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return $this->connection;
    }
}

final class RuntimeInjectionReporter implements AfterCommitFailureReporter
{
    /** @var list<AfterCommitFailure> */
    public array $failures = [];

    public function report(AfterCommitFailure $failure): void
    {
        $this->failures[] = $failure;
    }
}
