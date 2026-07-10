<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final readonly class DeferredWorkerRuntimeStorage
{
    public function __construct(
        public Connection $connection,
        public JournalRecordFactory $records,
        public CanonicalJournalWriter $journal,
        public PostgreSqlDeferredOperationLifecycleStore $state,
        public ClockInterface $clock,
        public LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        public ExecutionScopeProvider $scope = new ExecutionScopeProvider(),
    ) {}
}
