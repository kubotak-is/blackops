<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnostic;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipGuard;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;

final readonly class FrameworkProxyOperationOwnershipGuard
{
    public function assertFrameworkBinding(FrameworkProxyDefinitionBinding $binding): void
    {
        if ($binding->sourceClass !== $binding->metadata->sourceClass) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
        new FrameworkProxyOwnershipGuard()->assertCompatible($binding->metadata, $binding->marker);
        if (!$binding->marker->profile->equals(FrameworkProxyProfile::FRAMEWORK)) {
            throw $this->error(FrameworkProxyDiagnosticCode::MODE_CONFLICT, $binding);
        }
    }

    public function assertOperationMetadata(FrameworkProxyDefinitionBinding $binding, OperationMetadata $metadata): void
    {
        $this->assertFrameworkBinding($binding);
        if ($binding->marker->ownership !== FrameworkProxyOwnership::OPERATION) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
        if ($metadata->definition !== $binding->sourceClass) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
        $operationMethod = $binding->metadata->method('handle');
        if (
            $operationMethod?->transactional
            && $operationMethod->transactionalConnection !== $metadata->transactionConnection
        ) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
    }

    public function lifecycleOwned(FrameworkProxyDefinitionBinding $binding): bool
    {
        $this->assertFrameworkBinding($binding);

        return $binding->marker->ownership === FrameworkProxyOwnership::OPERATION && $binding->marker->lifecycleOwned;
    }

    public function assertTransactionalInvocation(
        FrameworkProxyDefinitionBinding $binding,
        string $method,
        ?string $connection,
    ): void {
        $this->assertFrameworkBinding($binding);
        $metadata = $binding->metadata->method($method);
        if ($metadata === null || !$metadata->transactional) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
        if ($metadata->transactionalConnection === null || $connection !== $metadata->transactionalConnection) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
    }

    public function assertAfterCommitInvocation(FrameworkProxyDefinitionBinding $binding, string $method): void
    {
        $this->assertFrameworkBinding($binding);
        $metadata = $binding->metadata->method($method);
        if ($metadata === null || !$metadata->afterCommit) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $binding);
        }
    }

    private function error(string $code, FrameworkProxyDefinitionBinding $binding): FrameworkProxyContractException
    {
        return new FrameworkProxyContractException(
            new FrameworkProxyDiagnostic($code, serviceId: $binding->serviceId, sourceClass: $binding->sourceClass),
        );
    }
}
