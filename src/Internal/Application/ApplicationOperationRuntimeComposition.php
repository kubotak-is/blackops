<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Journal\CanonicalJournalWriter;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final readonly class ApplicationOperationRuntimeComposition
{
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public string $applicationBuildId,
        public OperationRegistry $operations,
        public ContainerInterface $container,
        public DoctrineDatabaseManager $databases,
        public Connection $connection,
        public ClockInterface $clock,
        public IdentifierFactory $identifiers,
        public CanonicalJournalWriter $journal,
        public ExecutionScopeProvider $scope,
        public ExecutionScopedLogger $logger,
        public AuthorizationEvaluator $authorization,
        public OperationTransactionCoordinator $transactions,
        public ?ApplicationJournalObservations $observations,
        public ApplicationOperationInvocationLifecycle $lifecycle,
    ) {}
}
