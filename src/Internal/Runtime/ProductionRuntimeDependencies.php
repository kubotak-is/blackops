<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Journal\CanonicalJournalWriter;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;

final readonly class ProductionRuntimeDependencies
{
    /**
     * @param list<MiddlewareInterface> $httpMiddleware
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
    ) {}
}
