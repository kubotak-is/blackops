<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeQuery;
use BlackOps\OperationData\OperationOutcomeReadResult;
use BlackOps\OperationData\OperationOutcomeUnavailable;
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
final readonly class AuditedOperationOutcomeQuery implements OperationOutcomeQuery
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        OperationDataSubjectReader $subjects,
        #[\SensitiveParameter]
        OperationDataReadAuthorizer $authorizer,
        #[\SensitiveParameter]
        TenantScopedOutcomeReader $reader,
        #[\SensitiveParameter]
        OperationReadAuditSessionFactory $sessions,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'query' => new DefaultOperationOutcomeQuery(
                $subjects,
                new OperationDataAuditTenantAuthorizer($authorizer),
                $reader,
            ),
            'sessions' => $sessions,
            'connection' => $connection,
        ]);
    }

    public function find(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant,
        #[\SensitiveParameter]
        OperationDataPurpose $purpose,
    ): OperationOutcomeReadResult {
        $connection = $this->value('connection');
        if (!$connection instanceof Connection) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        $this->assertIdle($connection);

        $sessions = $this->value('sessions');
        if (!$sessions instanceof OperationReadAuditSessionFactory) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        try {
            $session = $sessions->begin(AuditReadPurpose::Outcome, $operationId, $currentActor, $currentTenant);
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        if (!$session instanceof OperationReadAuditSession) {
            throw OperationOutcomeQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        $result = null;
        $failure = null;
        try {
            $query = $this->value('query');
            if (!$query instanceof DefaultOperationOutcomeQuery) {
                throw OperationOutcomeQueryException::storageFailed();
            }
            $candidate = $query->find($operationId, $currentActor, $currentTenant, $purpose);
            if ($candidate instanceof OperationOutcomeFound || $candidate instanceof OperationOutcomeUnavailable) {
                $result = $candidate;
            } else {
                $failure = OperationOutcomeQueryException::integrityFailed();
            }
        } catch (OperationOutcomeQueryException $exception) {
            $failure = $exception;
        } catch (Throwable) {
            $failure = OperationOutcomeQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        try {
            if ($result instanceof OperationOutcomeFound) {
                $session->accessed();
            } else {
                $session->unavailable();
            }
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        if ($failure instanceof OperationOutcomeQueryException) {
            throw $failure;
        }
        if (!$result instanceof OperationOutcomeReadResult) {
            throw OperationOutcomeQueryException::integrityFailed();
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
        throw OperationOutcomeQueryException::storageFailed();
    }

    private function assertIdle(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw OperationOutcomeQueryException::storageFailed();
            }
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['query', 'sessions', 'connection']
            || !$value['query'] instanceof DefaultOperationOutcomeQuery
            || !$value['sessions'] instanceof OperationReadAuditSessionFactory
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw OperationOutcomeQueryException::storageFailed();
        }

        return $value[$key];
    }
}
