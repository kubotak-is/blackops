<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Core\ExecutionContext;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use Closure;
use Throwable;

/**
 * Keeps transaction ownership and callback queues in one coordinator so cleanup cannot diverge between paths.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class TransactionRuntime
{
    /** @var array<string, TransactionScope> */
    private array $scopes = [];

    /** @var list<string> */
    private array $transactionalInvocations = [];

    public function __construct(
        private readonly DatabaseManager $databases,
        private AfterCommitFailureReporter $reporter,
        private readonly ExecutionScopeProvider $executionScope,
    ) {}

    public function replaceReporter(AfterCommitFailureReporter $reporter): void
    {
        $this->reporter = $reporter;
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    public function transactional(string $connectionName, Closure $callback): mixed
    {
        $this->transactionalInvocations[] = $connectionName;

        try {
            $scope = $this->scopes[$connectionName] ?? null;

            if ($scope instanceof TransactionScope) {
                return $this->nested($scope, $callback);
            }

            return $this->root($connectionName, $callback);
        } finally {
            array_pop($this->transactionalInvocations);
        }
    }

    /** @param Closure(): void $callback */
    public function afterCommit(string $serviceClass, string $method, Closure $callback): void
    {
        $index = array_key_last($this->transactionalInvocations);
        $connectionName = $index === null ? null : $this->transactionalInvocations[$index];

        if ($connectionName === null) {
            $callback();

            return;
        }

        $scope = $this->scopes[$connectionName] ?? null;

        if (!$scope instanceof TransactionScope) {
            throw new \LogicException('Active transaction scope is unavailable.');
        }

        $scope->afterCommit[] = new AfterCommitInvocation($serviceClass, $method, $callback, $this->currentContext());
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    private function nested(TransactionScope $scope, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            $scope->rollbackOnly = true;

            throw $throwable;
        }
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    private function root(string $connectionName, Closure $callback): mixed
    {
        $connection = $this->databases->connection($connectionName);

        if ($connection->isTransactionActive()) {
            throw new TransactionException(
                'Cannot start an attributed transaction while a manual transaction is active.',
            );
        }

        $scope = new TransactionScope($connectionName, $connection);
        $this->scopes[$connectionName] = $scope;

        try {
            try {
                $connection->beginTransaction();
            } catch (Throwable $throwable) {
                throw new TransactionException('Database transaction could not be started.', previous: $throwable);
            }

            try {
                $result = $callback();
            } catch (Throwable $throwable) {
                $this->rollbackAfterFailure($scope);

                throw $throwable;
            }

            if ($connection->getTransactionNestingLevel() !== 1) {
                try {
                    $this->rollbackLeakedTransaction($scope);
                } catch (Throwable $throwable) {
                    throw new TransactionException('Database transaction rollback failed.', previous: $throwable);
                }

                throw new TransactionException('Attributed transaction changed the manual transaction nesting level.');
            }

            if ($scope->rollbackOnly) {
                $this->rollback($scope);

                throw new TransactionException('Database transaction was marked rollback-only.');
            }

            try {
                $connection->commit();
            } catch (Throwable $throwable) {
                $this->rollbackAfterFailure($scope);

                throw new TransactionException('Database transaction commit failed.', previous: $throwable);
            }

            $afterCommit = $scope->afterCommit;
            $scope->afterCommit = [];
            $this->removeScope($scope);
            $this->runAfterCommitOutsideCompletedRoot($connectionName, $afterCommit);

            return $result;
        } finally {
            $this->removeScope($scope);
        }
    }

    private function rollbackAfterFailure(TransactionScope $scope): void
    {
        try {
            $this->rollbackLeakedTransaction($scope);
        } catch (Throwable $rollbackFailure) {
            throw new TransactionException('Database transaction rollback failed.', previous: $rollbackFailure);
        } finally {
            $scope->afterCommit = [];
        }
    }

    private function rollback(TransactionScope $scope): void
    {
        try {
            $scope->connection->rollBack();
        } catch (Throwable $throwable) {
            throw new TransactionException('Database transaction rollback failed.', previous: $throwable);
        } finally {
            $scope->afterCommit = [];
        }
    }

    private function rollbackLeakedTransaction(TransactionScope $scope): void
    {
        $connection = $scope->connection;
        $remaining = max(1, $connection->getTransactionNestingLevel());

        while ($connection->isTransactionActive() && $remaining > 0) {
            $connection->rollBack();
            $remaining--;
        }

        $scope->afterCommit = [];
    }

    /** @param list<AfterCommitInvocation> $invocations */
    private function runAfterCommitOutsideCompletedRoot(string $connectionName, array $invocations): void
    {
        $active = array_pop($this->transactionalInvocations);

        if ($active !== $connectionName) {
            throw new \LogicException('Completed transaction does not match the logical invocation stack.');
        }

        try {
            $this->runAfterCommit($invocations);
        } finally {
            $this->transactionalInvocations[] = $connectionName;
        }
    }

    /** @param list<AfterCommitInvocation> $invocations */
    private function runAfterCommit(array $invocations): void
    {
        foreach ($invocations as $invocation) {
            try {
                ($invocation->callback)();
            } catch (Throwable $throwable) {
                try {
                    $this->reporter->report(
                        new AfterCommitFailure(
                            $invocation->serviceClass,
                            $invocation->method,
                            $throwable,
                            $invocation->context,
                        ),
                    );
                } catch (Throwable $reporterFailure) {
                    unset($reporterFailure);
                }
            }
        }
    }

    private function removeScope(TransactionScope $scope): void
    {
        if (($this->scopes[$scope->connectionName] ?? null) === $scope) {
            unset($this->scopes[$scope->connectionName]);
        }
    }

    private function currentContext(): ?ExecutionContext
    {
        return $this->executionScope->current()?->context();
    }
}
