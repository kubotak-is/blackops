<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

final readonly class MaintenanceSchedulerResult
{
    /**
     * @var list<MaintenanceTaskResult>
     */
    private array $taskResults;

    /**
     * @param list<MaintenanceTaskResult> $taskResults
     */
    public function __construct(array $taskResults)
    {
        $this->taskResults = $taskResults;
    }

    /**
     * @return list<MaintenanceTaskResult>
     */
    public function taskResults(): array
    {
        return $this->taskResults;
    }

    public function count(): int
    {
        return count($this->taskResults);
    }

    public function totalAffected(): int
    {
        $total = 0;

        foreach ($this->taskResults as $taskResult) {
            $total += $taskResult->affectedCount();
        }

        return $total;
    }
}
