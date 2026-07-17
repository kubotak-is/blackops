<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Database\DatabaseManager;
use Closure;
use Doctrine\DBAL\Connection;
use LogicException;

final readonly class OperationTransactionCoordinator
{
    public function __construct(
        private TransactionRuntime $transactions,
        private DatabaseManager $databases,
        private Connection $frameworkConnection,
    ) {}

    public function isTransactional(OperationMetadata $metadata): bool
    {
        return $metadata->transactionConnection !== null;
    }

    public function sharesFrameworkConnection(OperationMetadata $metadata): bool
    {
        $connectionName = $metadata->transactionConnection;

        return $connectionName !== null && $this->databases->connection($connectionName) === $this->frameworkConnection;
    }

    /**
     * @param Closure(): OperationResult $invoke
     * @param Closure(OperationResult): void $completeInSharedTransaction
     */
    public function execute(
        OperationMetadata $metadata,
        Closure $invoke,
        Closure $completeInSharedTransaction,
    ): OperationResult {
        $connectionName = $metadata->transactionConnection;
        if ($connectionName === null) {
            throw new LogicException('Operation transaction metadata is unavailable.');
        }

        try {
            return $this->transactions->transactional($connectionName, function () use (
                $metadata,
                $invoke,
                $completeInSharedTransaction,
            ): OperationResult {
                $result = $invoke();

                if (!$result->isCompleted()) {
                    throw new OperationTransactionRejected($result);
                }

                if ($this->sharesFrameworkConnection($metadata)) {
                    $completeInSharedTransaction($result);
                }

                return $result;
            });
        } catch (OperationTransactionRejected $rejected) {
            return $rejected->result;
        }
    }
}
