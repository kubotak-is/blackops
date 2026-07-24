<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlObserverReplayLoaded
{
    /** @param list<string> $targets */
    public function __construct(
        public PostgreSqlObserverReplaySelector $selector,
        public array $targets,
        public ?string $cursor,
    ) {}
}
