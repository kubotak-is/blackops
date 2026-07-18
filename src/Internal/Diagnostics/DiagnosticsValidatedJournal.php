<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Journal\LifecycleState;

final readonly class DiagnosticsValidatedJournal
{
    /**
     * @param list<DiagnosticsTimelineEntry> $timeline
     * @param list<DiagnosticsAttempt> $attempts
     */
    public function __construct(
        public DiagnosticsIdentity $identity,
        public LifecycleState $state,
        public array $timeline,
        public array $attempts,
    ) {}
}
