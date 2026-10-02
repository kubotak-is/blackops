<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Journal\JournalRecord;
use Doctrine\DBAL\Connection;

interface PostgreSqlObserverReplayPhaseHook
{
    public function assertEntry(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
    ): void;

    public function started(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
    ): void;

    public function progressed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $cursor,
        #[\SensitiveParameter]
        JournalRecord $source,
    ): void;

    public function finished(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $state,
    ): void;

    public function failed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $auditId,
    ): void;
}
