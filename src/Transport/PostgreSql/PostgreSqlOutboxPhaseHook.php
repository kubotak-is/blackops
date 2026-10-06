<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use Doctrine\DBAL\Connection;

interface PostgreSqlOutboxPhaseHook
{
    public function assertRelayEntry(#[\SensitiveParameter] Connection $connection): void;

    public function assertOperatorEntry(#[\SensitiveParameter] Connection $connection): void;

    /**
     * @param array<string,mixed> $row
     * DBAL must provide exactly record_id, tenant_type, tenant_id and
     * state_version; the Hook owns validation and closed-shape hydration.
     */
    public function retryScheduled(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        array $row,
    ): void;

    /**
     * @param array<string,mixed> $row
     * DBAL must provide exactly record_id, tenant_type, tenant_id and
     * state_version; the Hook owns validation and closed-shape hydration.
     */
    public function deadLettered(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        array $row,
    ): void;

    /**
     * @param array<string,mixed> $row
     * DBAL must provide exactly record_id, tenant_type, tenant_id and
     * state_version; the Hook owns validation and closed-shape hydration.
     */
    public function deadLetterRetryRequested(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $retryAuditId,
    ): void;
}
