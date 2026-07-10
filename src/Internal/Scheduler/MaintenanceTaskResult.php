<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use InvalidArgumentException;

final readonly class MaintenanceTaskResult
{
    public function __construct(
        private string $taskName,
        private int $affectedCount,
        private string $summary,
    ) {
        if (trim($taskName) === '') {
            throw new InvalidArgumentException('Maintenance task result name must not be empty.');
        }

        if ($affectedCount < 0) {
            throw new InvalidArgumentException('Maintenance task result affected count must not be negative.');
        }
    }

    public function taskName(): string
    {
        return $this->taskName;
    }

    public function affectedCount(): int
    {
        return $this->affectedCount;
    }

    public function summary(): string
    {
        return $this->summary;
    }
}
