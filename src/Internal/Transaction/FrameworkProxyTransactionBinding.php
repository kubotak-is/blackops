<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Execution\FrameworkProxyOperationOwnershipGuard;
use Closure;
use LogicException;

final readonly class FrameworkProxyTransactionBinding
{
    public function __construct(
        private TransactionRuntimeAccessor $transactions,
        private FrameworkProxyDefinitionBinding $binding,
        private FrameworkProxyOperationOwnershipGuard $ownership = new FrameworkProxyOperationOwnershipGuard(),
    ) {}

    /** @param array<int|string,mixed> $arguments */
    public function invoke(
        object $proxy,
        string $method,
        array $arguments,
        Closure $proceed,
        ?string $connection,
    ): mixed {
        $this->ownership->assertTransactionalInvocation($this->binding, $method, $connection);
        if ($this->binding->marker->ownership === FrameworkProxyOwnership::OPERATION) {
            return $proceed();
        }

        if ($connection === null) {
            throw new LogicException('Framework transaction connection is unresolved.');
        }

        return $this->transactions->transactional($connection, $proceed);
    }
}
