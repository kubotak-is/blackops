<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

final readonly class ProjectionBuildSetRecoveryResult
{
    public function __construct(
        public ProjectionBuildSetRecoveryStatus $status,
        public ?ProjectionBuildSetReadResult $read = null,
    ) {
        if (($status === ProjectionBuildSetRecoveryStatus::AVAILABLE) !== ($read !== null)) {
            throw new \InvalidArgumentException('Available recovery results require a read payload.');
        }
    }

    public function isAvailable(): bool
    {
        return $this->status === ProjectionBuildSetRecoveryStatus::AVAILABLE;
    }
}
