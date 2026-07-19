<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Journal\JournalRecord;

final readonly class OperationStatusSnapshot
{
    /**
     * @param list<JournalRecord> $records
     * @param array<string, true> $purgeTargets
     */
    public function __construct(
        public array $records,
        public array $purgeTargets,
        public bool $outcomeExists,
        public bool $deadLetterExists,
    ) {}

    public function wasPurged(string $target): bool
    {
        return array_key_exists($target, $this->purgeTargets);
    }
}
