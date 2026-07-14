# Maintenance Scheduler

The maintenance scheduler runs framework maintenance tasks that are not part of
normal operation dispatch. Retention purge is the first task shape supported by
this runtime.

The scheduler is internal. The Public Console Kernel composes it lazily from the
same Accepted Retention Policy used by manual Plan and Purge Commands.

## Execution Modes

BlackOps exposes two command classes:

| Command | Behavior |
| --- | --- |
| `scheduler:run` | Runs the registered maintenance tasks once, then exits. |
| `scheduler:daemon` | Runs the registered maintenance tasks repeatedly with an explicit interval. |

Use `scheduler:run` for cron, container scheduler, Kubernetes CronJob,
systemd timers, or other external schedulers.

Use `scheduler:daemon --interval=60` only when the application wants a
long-running process. The daemon intentionally does not own process supervision,
restart policy, or multi-start prevention.

The daemon also accepts `--iterations` for smoke tests or controlled local runs.
Without that option it keeps looping until the process is stopped.

## Task Registration

The composition root creates a `MaintenanceScheduler` with explicit task
instances:

```php
use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\RetentionMaintenanceTask;

$scheduler = new MaintenanceScheduler([
    new RetentionMaintenanceTask($purgeService, $policy, $policyRef, $actor),
]);
```

This keeps retention policy choice, policy reference, actor identity, database
connections, and process ownership in the application bootstrap boundary.

The Kernel does not instantiate or execute the task during construction, `list`,
or `help`. Only explicit scheduler command execution invokes the task.

## Retention Task

`RetentionMaintenanceTask` calls the injected retention purge service with the
configured policy, policy reference, actor, and scheduler timestamp. It returns a
small task result containing the task name and affected row count for command
output and operational logs.

The task does not decide whether it is safe to purge. Safety remains in the
retention planner, hold store, purge service, and the application's chosen
schedule.

## Multi-start Control

The current scheduler does not implement a database lock or file lock. External
orchestration should ensure that only the intended number of scheduler processes
run at once.

Framework-level lock support can be added later without changing the task
contract by wrapping scheduler execution before tasks are run.
