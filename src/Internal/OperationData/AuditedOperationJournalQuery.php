<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalQuery;
use BlackOps\OperationData\OperationJournalReadResult;
use BlackOps\OperationData\OperationJournalUnavailable;
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
final readonly class AuditedOperationJournalQuery implements OperationJournalQuery
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        OperationDataSubjectReader $subjects,
        #[\SensitiveParameter]
        OperationDataReadAuthorizer $authorizer,
        #[\SensitiveParameter]
        TenantScopedCanonicalJournalReader $reader,
        #[\SensitiveParameter]
        OperationReadAuditSessionFactory $sessions,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'query' => new DefaultOperationJournalQuery(
                $subjects,
                new OperationDataAuditTenantAuthorizer($authorizer),
                $reader,
            ),
            'sessions' => $sessions,
            'connection' => $connection,
        ]);
    }

    public function records(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant,
        #[\SensitiveParameter]
        OperationDataPurpose $purpose,
    ): OperationJournalReadResult {
        $connection = $this->value('connection');
        if (!$connection instanceof Connection) {
            throw OperationJournalQueryException::storageFailed();
        }
        $this->assertIdle($connection);

        $sessions = $this->value('sessions');
        if (!$sessions instanceof OperationReadAuditSessionFactory) {
            throw OperationJournalQueryException::storageFailed();
        }
        try {
            $session = $sessions->begin(AuditReadPurpose::Journal, $operationId, $currentActor, $currentTenant);
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
        if (!$session instanceof OperationReadAuditSession) {
            throw OperationJournalQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        $result = null;
        $failure = null;
        try {
            $query = $this->value('query');
            if (!$query instanceof DefaultOperationJournalQuery) {
                throw OperationJournalQueryException::storageFailed();
            }
            $candidate = $query->records($operationId, $currentActor, $currentTenant, $purpose);
            if ($candidate instanceof OperationJournalFound || $candidate instanceof OperationJournalUnavailable) {
                $result = $candidate;
            } else {
                $failure = OperationJournalQueryException::integrityFailed();
            }
        } catch (OperationJournalQueryException $exception) {
            $failure = $exception;
        } catch (Throwable) {
            $failure = OperationJournalQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        try {
            if ($result instanceof OperationJournalFound) {
                $session->accessed();
            } else {
                $session->unavailable();
            }
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }

        $this->assertIdle($connection);

        if ($failure instanceof OperationJournalQueryException) {
            throw $failure;
        }
        if (!$result instanceof OperationJournalReadResult) {
            throw OperationJournalQueryException::integrityFailed();
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
        throw OperationJournalQueryException::storageFailed();
    }

    private function assertIdle(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw OperationJournalQueryException::storageFailed();
            }
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['query', 'sessions', 'connection']
            || !$value['query'] instanceof DefaultOperationJournalQuery
            || !$value['sessions'] instanceof OperationReadAuditSessionFactory
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw OperationJournalQueryException::storageFailed();
        }

        return $value[$key];
    }
}
