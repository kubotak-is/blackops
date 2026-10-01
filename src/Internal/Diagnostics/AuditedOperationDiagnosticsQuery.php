<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Outcome\OutcomeReader;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Keeps terminal persistence ahead of releasing a result or exception.
 *
 * The failure matrix stays in one boundary so terminal persistence must finish
 * before the result or exception is released.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:halstead
 * @mago-expect lint:no-else-clause
 */
final readonly class AuditedOperationDiagnosticsQuery
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        CanonicalJournalReader $journal,
        #[\SensitiveParameter]
        OutcomeReader $outcomes,
        #[\SensitiveParameter]
        DiagnosticsSourceReader $sources,
        #[\SensitiveParameter]
        OperationDiagnosticsAuthorizer $authorizer,
        #[\SensitiveParameter]
        OperationReadAuditSessionFactory $sessions,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'query' => new OperationDiagnosticsQuery($journal, $outcomes, $sources),
            'authorizer' => $authorizer,
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
    ): OperationDiagnosticsResult {
        $connection = $this->value('connection');
        if (!$connection instanceof Connection) {
            throw OperationDiagnosticsException::storageFailed();
        }
        $this->assertIdle($connection);

        $sessions = $this->value('sessions');
        if (!$sessions instanceof OperationReadAuditSessionFactory) {
            throw OperationDiagnosticsException::storageFailed();
        }
        try {
            $session = $sessions->begin(AuditReadPurpose::Diagnostics, $operationId, $currentActor, $currentTenant);
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }
        if (!$session instanceof OperationReadAuditSession) {
            throw OperationDiagnosticsException::storageFailed();
        }

        $this->assertIdle($connection);

        $result = null;
        $failure = null;
        try {
            if ($currentTenant !== null) {
                $result = new OperationDiagnosticsUnavailable();
            } else {
                $authorizer = $this->value('authorizer');
                if (!$authorizer instanceof OperationDiagnosticsAuthorizer) {
                    throw OperationDiagnosticsException::storageFailed();
                }
                $decision = $authorizer->decide(new OperationDiagnosticsAuthorizationRequest(
                    $operationId,
                    $currentActor,
                    $currentTenant,
                ));
                if (!$decision instanceof AuditReadAuthorizationDecision) {
                    throw OperationDiagnosticsException::storageFailed();
                }
                if ($decision !== AuditReadAuthorizationDecision::Allow) {
                    $result = new OperationDiagnosticsUnavailable();
                }
            }
        } catch (Throwable) {
            $failure = DiagnosticsFailureCode::StorageFailed;
        }

        $this->assertIdle($connection);

        if ($failure === null && $result === null) {
            try {
                $query = $this->value('query');
                if (!$query instanceof OperationDiagnosticsQuery) {
                    throw OperationDiagnosticsException::storageFailed();
                }
                $candidate = $query->find($operationId);
                if (
                    $candidate instanceof OperationDiagnosticsFound
                    || $candidate instanceof OperationDiagnosticsUnavailable
                ) {
                    $result = $candidate;
                } else {
                    $failure = DiagnosticsFailureCode::IntegrityFailed;
                }
                unset($candidate);
            } catch (OperationDiagnosticsException $exception) {
                try {
                    $failure = $exception->diagnosticsCode;
                    if (!$failure instanceof DiagnosticsFailureCode) {
                        $failure = DiagnosticsFailureCode::StorageFailed;
                    }
                } catch (Throwable) {
                    $failure = DiagnosticsFailureCode::StorageFailed;
                }
                unset($exception);
            } catch (Throwable) {
                $failure = DiagnosticsFailureCode::StorageFailed;
            }

            $this->assertIdle($connection);
        }

        try {
            if ($result instanceof OperationDiagnosticsFound) {
                $session->accessed();
            } else {
                $session->unavailable();
            }
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }

        $this->assertIdle($connection);

        if ($failure === DiagnosticsFailureCode::StorageFailed) {
            throw OperationDiagnosticsException::storageFailed();
        }
        if ($failure === DiagnosticsFailureCode::DecodeFailed) {
            throw OperationDiagnosticsException::decodeFailed();
        }
        if ($failure === DiagnosticsFailureCode::IntegrityFailed) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if (!$result instanceof OperationDiagnosticsResult) {
            throw OperationDiagnosticsException::integrityFailed();
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
        throw OperationDiagnosticsException::storageFailed();
    }

    private function assertIdle(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw OperationDiagnosticsException::storageFailed();
            }
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['query', 'authorizer', 'sessions', 'connection']
            || !$value['query'] instanceof OperationDiagnosticsQuery
            || !$value['authorizer'] instanceof OperationDiagnosticsAuthorizer
            || !$value['sessions'] instanceof OperationReadAuditSessionFactory
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw OperationDiagnosticsException::storageFailed();
        }

        return $value[$key];
    }
}
