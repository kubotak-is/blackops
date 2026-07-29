<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

final readonly class ScheduleEvaluationResult
{
    /** @param list<ScheduleOccurrence> $occurrences */
    public function __construct(
        public array $occurrences,
        public bool $claimed,
    ) {}
}
