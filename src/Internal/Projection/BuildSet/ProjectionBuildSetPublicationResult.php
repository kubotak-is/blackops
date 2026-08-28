<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

final readonly class ProjectionBuildSetPublicationResult
{
    /**
     * @param list<string> $deleted
     */
    public function __construct(
        public bool $published,
        public bool $cleanupComplete,
        public string $applicationBuildId,
        public string $buildSetHash,
        public array $deleted = [],
    ) {}

    public function isPartialCleanup(): bool
    {
        return $this->published && !$this->cleanupComplete;
    }
}
