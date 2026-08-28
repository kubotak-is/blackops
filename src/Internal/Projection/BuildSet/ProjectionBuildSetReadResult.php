<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

final readonly class ProjectionBuildSetReadResult
{
    /**
     * @param array<string, ProjectionBuildSetProjection> $projections
     */
    public function __construct(
        public string $applicationBuildId,
        public string $buildSetHash,
        public array $projections,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function records(string $key): array
    {
        if (!array_key_exists($key, $this->projections)) {
            throw new ProjectionBuildSetValidationException('Projection key is unsupported.');
        }

        return $this->projections[$key]->records;
    }
}
