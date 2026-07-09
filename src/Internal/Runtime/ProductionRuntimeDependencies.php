<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Journal\CanonicalJournalWriter;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ProductionRuntimeDependencies
{
    public function __construct(
        public ClockInterface $clock,
        public CanonicalJournalWriter $journal,
        public ResponseFactoryInterface $responses,
        public StreamFactoryInterface $streams,
        public ?ExecutionScopeProvider $executionScope = null,
        public ?JournalObservationPipeline $journalObservations = null,
    ) {}
}
