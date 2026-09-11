<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusUnavailable;
use BlackOps\Status\TenantAwareOperationStatusQuery;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Keeps terminal persistence ahead of releasing a result or exception.
 *
 * The failure matrix stays in one boundary so terminal persistence must finish
 * before the result or exception is released.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:no-else-clause
 */
final readonly class AuditedOperationStatusQuery implements TenantAwareOperationStatusQuery
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        TenantScopedOperationStatusSource $source,
        #[\SensitiveParameter]
        OperationStatusAuthorizer $authorizer,
        #[\SensitiveParameter]
        OperationReadAuditSessionFactory $sessions,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'source' => $source,
            'authorizer' => new OperationStatusAuditTenantAuthorizer($authorizer),
            'sessions' => $sessions,
            'connection' => $connection,
        ]);
    }

    public function find(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor = null,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant = null,
    ): OperationStatusResult {
        return $this->findForTenant($operationId, $currentActor, $currentTenant);
    }

    public function findForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor = null,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant = null,
    ): OperationStatusResult {
        $connection = $this->value('connection');
        if (!$connection instanceof Connection) {
            throw OperationStatusQueryException::storageFailed();
        }
        $this->assertIdle($connection);

        $sessions = $this->value('sessions');
        if (!$sessions instanceof OperationReadAuditSessionFactory) {
            throw OperationStatusQueryException::storageFailed();
        }
        try {
            $session = $sessions->begin(AuditReadPurpose::Status, $operationId, $currentActor, $currentTenant);
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }
        if (!$session instanceof OperationReadAuditSession) {
            throw OperationStatusQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        $result = null;
        $failure = null;
        try {
            $source = $this->value('source');
            $authorizer = $this->value('authorizer');
            if (
                !$source instanceof TenantScopedOperationStatusSource
                || !$authorizer instanceof OperationStatusAuthorizer
            ) {
                throw OperationStatusQueryException::storageFailed();
            }
            $boundSource = new TenantBoundOperationStatusSource($source, $currentTenant);
            $query = new DefaultOperationStatusQuery($boundSource, $authorizer);
            $candidate = $query->findForTenant($operationId, $currentActor, $currentTenant);
            if (
                $candidate instanceof OperationStatusFound
                || $candidate instanceof OperationStatusExpired
                || $candidate instanceof OperationStatusUnavailable
            ) {
                $result = $candidate;
            } else {
                $failure = OperationStatusQueryException::integrityFailed();
            }
        } catch (OperationStatusQueryException $exception) {
            $failure = $exception;
        } catch (Throwable) {
            $failure = OperationStatusQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        try {
            if ($result instanceof OperationStatusFound || $result instanceof OperationStatusExpired) {
                $session->accessed();
            } else {
                $session->unavailable();
            }
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        if ($failure instanceof OperationStatusQueryException) {
            throw $failure;
        }
        if (!$result instanceof OperationStatusResult) {
            throw OperationStatusQueryException::integrityFailed();
        }

        return $result;
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw OperationStatusQueryException::storageFailed();
    }

    private function assertIdle(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw OperationStatusQueryException::storageFailed();
            }
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['source', 'authorizer', 'sessions', 'connection']
            || !$value['source'] instanceof TenantScopedOperationStatusSource
            || !$value['authorizer'] instanceof OperationStatusAuditTenantAuthorizer
            || !$value['sessions'] instanceof OperationReadAuditSessionFactory
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw OperationStatusQueryException::storageFailed();
        }

        return $value[$key];
    }
}
