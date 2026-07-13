# Deferred Worker Runtime

The deferred worker processes one claim at a time. Each loop iteration first attempts to recover one expired running attempt, then claims one eligible operation. If no claim is available, the worker sleeps for the configured idle interval.

`DeferredWorkerRuntime` commits attempt start before entering the handler guard. It commits completion or rejection only after the guard returns normally. Handler exceptions use the normal failure-supervision boundary. After supervision is durably recorded, the runtime wraps the original handler exception as a `SupervisedHandlerFailureException`; only this marker is eligible for loop continuation. Metadata, transaction, recovery, claim, completion, and settlement failures are infrastructure failures and terminate the worker instead of being swallowed by the handler-failure policy.

The common handler invoker selects the invocation contract from compiled metadata. Typed Self-handled definitions receive their declared `OperationValue` and, when requested, an `ExecutionContext` containing the Operation ID and current Attempt. Native Outcome and Void returns are normalized to internal OperationResult; `OperationRejectedException` becomes the existing terminal Rejected lifecycle. Other Throwable values remain in the normal supervision boundary. Legacy Self-handled and Separate `OperationHandler` implementations continue to receive the complete Envelope. The invoker performs no source discovery or runtime signature inference.

A heartbeat failure or grace-period timeout throws a `WorkerExecutionInterruptedException`; the runtime deliberately bypasses normal handler supervision, and the loop performs no acknowledge or release. The running claim is left for lease-expiry recovery by another worker.

Successful completion also saves an `OutcomeRecord` through the `OutcomeWriter` in `DeferredWorkerRuntimeStorage`. The writer must use the same DBAL connection as lifecycle state and canonical journal storage. State, completion journal records, and the typed outcome then commit atomically; an outcome failure rolls back all three. Rejected and supervised failure paths do not write outcomes.

## Signal and heartbeat composition

`PcntlSignalHeartbeat` serves two roles and the same instance must be supplied to both boundaries:

- `ClaimExecutionGuard` on `DeferredWorkerRuntime`, so alarms are active only while application handler code runs.
- `WorkerSignalRuntime` on `DeferredWorkerLoop`, so process signal handlers are installed for the life of the loop.

The heartbeat adapter must use a dedicated DBAL connection. Do not reuse the connection used by claim, lifecycle, journal, recovery, or settlement transactions. A signal can interrupt synchronous handler code at any point; an independent connection prevents the heartbeat query from re-entering an in-progress DBAL operation.

```php
use BlackOps\Internal\Console\WorkerRunCommand;
use BlackOps\Internal\Execution\DeferredWorkerLoop;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\PcntlSignalHeartbeat;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use Doctrine\DBAL\DriverManager;

$workerConnection = DriverManager::getConnection($databaseParameters);
$heartbeatConnection = DriverManager::getConnection($databaseParameters);

$receiver = new PostgreSqlDeferredOperationReceiver(
    $workerConnection,
    schema: 'blackops',
    leaseOwner: $workerId,
    leaseSeconds: 30,
);
$heartbeat = new PostgreSqlDeferredOperationReceiver(
    $heartbeatConnection,
    schema: 'blackops',
    leaseOwner: $workerId,
    leaseSeconds: 30,
);

$signals = new PcntlSignalHeartbeat(
    heartbeat: $heartbeat,
    heartbeatSeconds: 10,
    leaseSeconds: 30,
    graceSeconds: 20,
);

$runtime = new DeferredWorkerRuntime(
    services: $runtimeServices,
    storage: $runtimeStorage,
    guard: $signals,
);

$loop = new DeferredWorkerLoop(
    recovery: $expiredAttemptRecovery,
    receiver: $receiver,
    runtime: $runtime,
    settlement: $receiver,
    signals: $signals,
    clock: $clock,
);

$application->add(new WorkerRunCommand($loop));
```

The connection objects above may share database parameters and a server, but they must be distinct DBAL `Connection` instances. The heartbeat interval must be positive and shorter than the lease duration.

## Process lifecycle

Run the registered command as a long-lived process:

```bash
php bin/console blackops:worker:run --idle-sleep-milliseconds=1000
```

`--iterations=N` provides a finite loop for smoke tests and controlled jobs. Production normally omits it and relies on a process supervisor.

PCNTL is required only when the worker signal runtime is constructed. Missing PCNTL functions fail fast without affecting HTTP or build commands. The reference Docker image enables PCNTL.

The Public Console Kernel constructs this graph only when `blackops:worker:run` executes. It loads Compile済みArtifact without fallback, creates Main and Heartbeat DBAL Connections separately from the same parameters, and passes one `PcntlSignalHeartbeat` Instance to both the Runtime Guard and Loop Signal boundary. Kernel construction、`list`、`help`、Build do not check PCNTL availability.

`SIGTERM` and `SIGINT` stop new claims. If no handler is active, the loop exits at its next boundary. If a handler is active, it may finish within the configured grace period and is then acknowledged normally. When the grace period expires, execution is interrupted; the worker does not acknowledge or release the claim. The claim remains running until its lease expires and normal expired-attempt recovery takes ownership.

The signal runtime cancels its alarm and restores the previous `SIGALRM`, `SIGTERM`, `SIGINT`, and asynchronous-signal settings when the loop exits. Applications should still restart the process after a claim-lost or grace-timeout failure rather than attempting to continue it.
