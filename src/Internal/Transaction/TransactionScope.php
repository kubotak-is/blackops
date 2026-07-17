<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use Doctrine\DBAL\Connection;

final class TransactionScope
{
    public bool $rollbackOnly = false;

    /** @var list<AfterCommitInvocation> */
    public array $afterCommit = [];

    public function __construct(
        public readonly string $connectionName,
        public readonly Connection $connection,
    ) {}
}
