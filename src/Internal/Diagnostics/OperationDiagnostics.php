<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class OperationDiagnostics
{
    /**
     * @param list<DiagnosticsTimelineEntry> $timeline
     * @param list<DiagnosticsAttempt> $attempts
     */
    public function __construct(
        public DiagnosticsIdentity $identity,
        public DiagnosticsState $state,
        public DiagnosticsAvailabilitySet $availability,
        public array $timeline,
        public array $attempts,
        public ?DiagnosticsOutcome $outcome,
    ) {}
}
