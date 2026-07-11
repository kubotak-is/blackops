<?php

declare(strict_types=1);

namespace BlackOps\Internal\Migration;

final readonly class DatabaseMigrationStatus
{
    /**
     * @param list<string> $appliedVersions
     * @param list<string> $pendingVersions
     */
    public function __construct(
        public array $appliedVersions,
        public array $pendingVersions,
    ) {}
}
