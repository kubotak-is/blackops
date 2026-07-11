<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

final readonly class DatabaseMigrationResult
{
    /** @param list<string> $sql */
    public function __construct(
        public bool $dryRun,
        public int $migrations,
        public array $sql,
    ) {}
}
