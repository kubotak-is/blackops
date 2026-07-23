<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Status\OperationStatusQuery;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;

final readonly class ProductionRuntimeDependencies
{
    /**
     * @param list<MiddlewareInterface> $httpMiddleware
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        public ClockInterface $clock,
        public CanonicalJournalWriter $journal,
        public ResponseFactoryInterface $responses,
        public StreamFactoryInterface $streams,
        public ?ExecutionScopeProvider $executionScope = null,
        public ?JournalObservationPipeline $journalObservations = null,
        public ?DeferredOperationAcceptor $deferredOperationAcceptor = null,
        public array $httpMiddleware = [],
        public ?OperationTransactionCoordinator $operationTransactions = null,
        public ?ExecutionScopedLogger $executionLogger = null,
        public ?OperationStatusQuery $operationStatusQuery = null,
        public ?IdempotencyStore $idempotencyStore = null,
        public ?RetentionPeriod $idempotencyRetention = null,
        public ?CanonicalJournalReader $journalReader = null,
        public ?Connection $idempotencyConnection = null,
        public string $idempotencySchema = 'blackops',
    ) {}
}
