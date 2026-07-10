<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use DateTimeImmutable;

final readonly class MaintenanceScheduler
{
    /**
     * @var list<MaintenanceTask>
     */
    private array $tasks;

    /**
     * @param iterable<MaintenanceTask> $tasks
     */
    public function __construct(iterable $tasks)
    {
        $normalized = [];

        foreach ($tasks as $task) {
            $normalized[] = $task;
        }

        $this->tasks = $normalized;
    }

    public function run(DateTimeImmutable $now): MaintenanceSchedulerResult
    {
        $results = [];

        foreach ($this->tasks as $task) {
            $results[] = $task->run($now);
        }

        return new MaintenanceSchedulerResult($results);
    }
}
