# P20-018D Completion Report

Status: Accepted

## Summary

Added an API-only, application-owned OpenTelemetry MeterProvider boundary with an immutable per-Application snapshot, official No-op fallback, `blackops.framework` scope version `1.1.0`, stable instrument declarations, instrument-specific finite attribute projection, active-operation balance scope, and failure-isolated instrument creation/recording. Operation, worker attempt, maintenance, scheduler, outbox relay, observer aggregator/replay, and application runtime composition now accept the metric adapter without changing primary lifecycle results.

## Changed Files

- `src/Internal/Telemetry/TelemetryMetrics.php`, `src/Internal/Telemetry/TelemetryMetricScope.php` — official metric adapter, stable instrument matrix, typed finite allowlist projection, active counter balance and failure isolation.
- `src/Application/ApplicationBuilder.php`, `src/Internal/Application/ApplicationConfigurationSnapshot.php` — typed MeterProvider registration and immutable snapshot binding.
- `src/Internal/Execution/ExecutionScopeProvider.php`, `src/Internal/Application/**`, `src/Internal/Outbox/**`, `src/Internal/Scheduler/**`, `src/Internal/Scheduling/**`, `src/Internal/Replay/**` — metric composition and runtime boundary wiring; runtime setup and deferred-attempt preparation use small helpers to keep quality gates clean.
- `tests/Internal/Telemetry/**`, `tests/Internal/Application/**` — recording meter provider, matrix/cardinality/balance/no-op evidence, provider isolation.
- `src/Internal/StorageProtection/BopdEnvelopeCodec.php`, `src/Internal/Journal/JournalObserverAggregator.php`, `src/Internal/Replay/ObserverReplayTargetRegistry.php` — compiled codec reuse and observer failure recording.
- `tests/Application/ApplicationTest.php` — public `ApplicationBuilder` fluent-shape expectation synchronized with `withMeterProvider`.
- `develop/spec/09-runtime-and-di.md`, workflow checkpoint files.

## Instrument / Attribute Matrix

| Instrument | Type | UCUM Unit | Attributes |
| --- | --- | --- | --- |
| `blackops.operation.duration` | Histogram | `s` | operation type, strategy, runtime kind, result |
| `blackops.operation.active` | UpDownCounter | `{operation}` | operation type, strategy, runtime kind |
| `blackops.worker.claims` | Counter | `{claim}` | result |
| `blackops.worker.heartbeat.failures` | Counter | `{failure}` | failure code |
| `blackops.outbox.relay.duration` | Histogram | `s` | result |
| `blackops.outbox.relay.records` | Counter | `{record}` | result |
| `blackops.scheduler.run.duration` | Histogram | `s` | scheduler kind, result |
| `blackops.scheduler.occurrences` | Counter | `{occurrence}` | scheduler kind, result |
| `blackops.observer.failures` | Counter | `{failure}` | observer kind, failure code |
| `blackops.storage.protection.failures` | Counter | `{failure}` | storage purpose, failure code |

Identity fields, tenant/actor values, operation/attempt/correlation/trace IDs, schedule names, occurrence/record IDs, URLs, payloads, free-form reasons, and throwable details are excluded from metric attributes.

## Cardinality / Balance / Failure Evidence

- `TelemetryMetricsTest::testCreatesStableInstrumentMatrixAndBalancesActiveCounter` — recording provider captures exact names, types, units, finite attributes, rejects identity labels, and observes active `+1`/`-1` balance.
- `TelemetryMetricsTest::testOperationResultMatrixHasExactAttributesAndBalancesActive` — every finite terminal result (`completed`, `rejected`, `retry_scheduled`, `dead_lettered`, `failed`, `interrupted`) records the exact duration/result and active attribute sets with balanced `+1`/`-1` updates.
- `TelemetryMetricsTest::testThrowingProviderFallsBackToNoopWithoutChangingLifecycle` — provider creation failure is swallowed and lifecycle scope still closes.
- `ApplicationBuilderTest::testBuilderBindsMeterProviderIntoCreatedApplicationSnapshot` and `ApplicationTelemetryProviderTest::testMeterProviderIsBoundToEachImmutableConfigurationSnapshot` — typed per-Application provider isolation.
- Runtime composition wires operation, maintenance, scheduler, relay, replay, worker claim, and worker heartbeat metric boundaries; each metric scope ends in `finally` and suppresses recording failures.
- `DeferredWorkerLoopTest::testRecordsWorkerClaimResultWithoutChangingSettlement` and `SignalHeartbeatTest::testHeartbeatFailureInterruptsHandlerAsClaimLost` prove claim result and safe heartbeat failure recording without changing settlement or interruption behavior.
- `BopdEnvelopeCodecTest::testProviderFailureRecordsOnlySafeProtectionMetricAttributes` proves encryption failure emits only finite storage purpose and failure code attributes.
- Outbox relay records/duration, scheduler occurrences/duration, observer failures, and storage protection failures are instrumented at their runtime boundaries with finite result/kind/code projection.
- `OutboxRelayRuntimeTest::testRetryBackoffDeadLetterFingerprintAndBatchIsolationAreSafe` and `ScheduledOperationRunnerTest::testRunsSchedulesInNameOrderRecoversBeforeEvaluationAndTerminalizesClaimFailure` capture relay record/duration and scheduler occurrence/duration datapoints alongside existing lifecycle assertions.
- `DeferredWorkerRuntimeTest::testRetryablePolicyFailureIsReevaluatedOnNextAttempt` captures `retry_scheduled` then `completed` attempt datapoints and preserves retry lifecycle; `ExecutionScopeProviderTest::testRejectedOperationResultIsRecordedAsRejected` captures rejected result and active balance.
- `DeferredWorkerLoopTest::testWorkerClaimResultMatrixUsesSafeTerminalValues` covers rejected claims, retry/dead-letter supervision, invalid supervision fallback, and all worker interruption exception classes without changing settlement behavior.
- `MaintenanceSchedulerTest::testRethrowsTaskFailureAndRecordsFailedSchedulerMetrics` verifies original throwable identity/message and exact failed duration/occurrence attributes; `JournalObserverAggregatorTest::testReplayObserverFailureRecordsExactSafeMetric` and `::testReplayFlushFailureRecordsExactSafeMetric` verify replay observe/flush failure codes.
- `ExecutionScopeProviderTest::testThrowingMeterProviderPreservesCallbackResultsAndStackCleanup` verifies provider failure isolation, rejected callback result preservation, original throwable propagation, and stack cleanup.

## Commands and Results

- Required focused PHPUnit (`tests/Internal/Telemetry`、Execution、Outbox、Scheduling、Scheduler、Projection、StorageProtection) — passed independently after the final refactor: 223 tests, 1,165 assertions (existing deprecation/notices only).
- Metric/worker/protection focused PHPUnit — passed: 73 tests, 663 assertions (existing PHPUnit notices/deprecation/notices only).
- Internal application/journal/replay/outbox/scheduling focused PHPUnit — passed: 469 tests, 1,976 assertions (existing PHPUnit deprecation/notices only).
- Full PHPUnit — first run exposed one contract-test mismatch (`tests/Application/ApplicationTest.php`, missing `withMeterProvider` in the expected public method list); after the test-only correction, passed: 2,164 tests, 8,990 assertions (existing deprecation/notices only).
- Final post-refactor Full PHPUnit — passed independently: 2,164 tests, 8,990 assertions (deprecation 1, PHPUnit deprecations 2, notices 13).
- Quickstart Consumer E2E — passed independently after the final production refactor.
- Framework package export — pre-commit contract passed; after commit `4a11be7`, the exact Git/Composer export contract passed again from committed `HEAD`.
- `composer validate --strict` and `composer audit --locked --no-dev` — passed; `composer.json` is valid and no security advisories were found.
- Post-refactor focused internal suite — passed: 469 tests, 1,976 assertions; full `mago format --check src tests`, `git diff --check`, and management-ID guard passed.
- Runtime Outbox/Scheduling focused PHPUnit — passed: 112 tests, 395 assertions (existing PHPUnit notices/deprecation/notices only).
- Targeted quality-correction source `mago lint`／`mago analyze` — `INFO No issues found.` after typed attribute projection and runtime helper extraction. The complete changed production set has no lint/analyze errors; two pre-existing Outbox lint help messages and two resolver mixed-assignment warnings remain.
- Broad `mago lint` — known repository baseline: 75 issues (7 errors, 25 warnings, 29 notes, 14 help); no P20-018D warning-count drift remains. Broad `mago analyze` — known baseline 24 mixed-assignment warnings; no P20-018D warning-count drift remains.
- Deptrac — blocked by the existing PHP 8.5 vendor parser failure at `vendor/deptrac/deptrac/src/DefaultBehavior/Ast/Parser/Helpers/NikicFileReferenceVisitor.php:106`; no project dependency violation result was produced.
- `git diff --check` and management-ID guard — passed.
- Full source/test formatting — Docker `mago format --check src tests` passed.

## Acceptance Criteria

- [x] Stable instrument names/types/units and finite attribute projection are declared and exact-tested.
- [x] Typed per-Application MeterProvider registration, compiled codec reuse, and official No-op fallback are implemented and tested.
- [x] Active operation metric balances on success/failure scope exits and recording/provider failures are isolated.
- [x] Worker attempt/claim/heartbeat, outbox records/duration, scheduler occurrences/duration, observer aggregator/replay failures, and storage protection failure paths have independent focused recording evidence.
- [x] Full PHPUnit suite passes after synchronizing the public fluent-shape contract test.
- [x] Consumer, Composer, package export, format, management-ID, and diff gates pass; broad Mago and Deptrac are separated as unchanged repository/tool baselines.
- [x] Optional queue snapshots are deliberately not added and therefore N/A under the conditional contract.
- [x] Report/STATE/TODO are synchronized to Accepted; Worker did not commit.

## Remaining Issues

Optional queue snapshots remain deliberately unimplemented and are N/A. Broad Mago retains its known repository baseline, and Deptrac remains blocked by its PHP 8.5 vendor parser. The Orchestrator HTTP integration evidence is 6 tests/73 assertions without a direct HTTP metric datapoint assertion; actual SDK/exporter transmission and Collector datapoint evidence remain P20-018E.

## Suggested Next Action

Orchestrator should commit P20-018D, rerun the exact Framework package export from committed `HEAD`, then start P20-018E for explicit health adapters and the pinned local Docker Collector journey.
