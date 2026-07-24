<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use DateTimeImmutable;

final readonly class PostgreSqlObserverReplayBeginRequest
{
    /** @param list<string> $targets */
    public function __construct(
        public string $checkpoint,
        public PostgreSqlObserverReplaySelector $selector,
        public array $targets,
        public string $actor,
        public string $reason,
        public ?DateTimeImmutable $now = null,
    ) {}
}
