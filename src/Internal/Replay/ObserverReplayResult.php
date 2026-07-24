<?php

declare(strict_types=1);

namespace BlackOps\Internal\Replay;

final readonly class ObserverReplayResult
{
    /** @param list<string> $recordIds */
    public function __construct(
        public int $selected,
        public int $delivered,
        public int $failed,
        public bool $hasMore,
        public bool $complete,
        public array $recordIds = [],
    ) {}
}
