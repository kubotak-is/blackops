<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsAvailabilitySet
{
    public function __construct(
        public DiagnosticsAvailability $transportPayload,
        public DiagnosticsAvailability $journal,
        public DiagnosticsAvailability $outcome,
        public DiagnosticsAvailability $deadLetter,
    ) {}
}
