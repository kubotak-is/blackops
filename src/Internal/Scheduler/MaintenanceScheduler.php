<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use BlackOps\Internal\Telemetry\TelemetryTracer;
use DateTimeImmutable;
use Throwable;

final readonly class MaintenanceScheduler
{
    /**
     * @var list<MaintenanceTask>
     */
    private array $tasks;

    /**
     * @param iterable<MaintenanceTask> $tasks
     */
    public function __construct(
        iterable $tasks,
        private ?TelemetryTracer $telemetry = null,
    ) {
        $normalized = [];

        foreach ($tasks as $task) {
            $normalized[] = $task;
        }

        $this->tasks = $normalized;
    }

    public function run(DateTimeImmutable $now): MaintenanceSchedulerResult
    {
        $span = $this->telemetry?->start('blackops.maintenance.run', attributes: [
            'blackops.runtime.kind' => 'maintenance',
        ]);
        try {
            $result = $this->runTasks($now);
            $span?->result('completed');
            return $result;
        } catch (Throwable $failure) {
            $span?->fail($failure);
            throw $failure;
        } finally {
            $span?->end();
        }
    }

    private function runTasks(DateTimeImmutable $now): MaintenanceSchedulerResult
    {
        $results = [];

        foreach ($this->tasks as $task) {
            $results[] = $task->run($now);
        }

        return new MaintenanceSchedulerResult($results);
    }
}
