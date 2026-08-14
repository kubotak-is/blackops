# P20-018C Completion Report

Status: Accepted

## Summary

Implemented the application-owned OpenTelemetry trace adapter with official OpenTelemetry API types, immutable per-application provider binding, deterministic instrumentation scope `blackops.framework` version `1.1.0`, active-span parent preference, persisted trace-context fallback, bounded safe attributes, and failure-isolated span cleanup. The correction pass keeps HTTP and outbox Producer scopes through actual acceptance/insert, carries one Consumer span across worker attempt-start through terminal/supervision records, sets finite result values, projects fully masked actor/tenant attributes, and ends partial spans when activation fails.

Producer, internal, consumer, schedule, maintenance, outbox relay, and observer replay boundaries now share the runtime tracer selected from the configuration snapshot. Deferred and outbox contexts are encoded after producer context propagation, while journal and JSONL correlation prefers a valid active Framework span and preserves persisted correlation for replay.

## Changed Files

- `src/Internal/Telemetry/**` — official typed tracer and span scope; no custom reflection adapter or process-global registration.
- `src/Application/ApplicationBuilder.php`, `src/Internal/Application/**` — provider registration, immutable snapshot binding, and runtime composition wiring.
- `src/Internal/Execution/**`, `src/Internal/ExecutionContext/**`, `src/Internal/Http/**`, `src/Internal/Outbox/**`, `src/Internal/Scheduling/**`, `src/Internal/Scheduler/**`, `src/Internal/Replay/**`, `src/Internal/Journal/**`, `src/Internal/Logging/**` — lifecycle boundaries, context propagation, active correlation, and cleanup.
- `tests/**` — official OpenTelemetry fake/mocked provider coverage, active parent and flags/tracestate coverage, provider isolation, partial activation cleanup, encrypted deferred propagation, outbox context propagation, worker lifecycle, and active Journal correlation.
- `mago.toml`, `develop/spec/100-structured-logging-and-opentelemetry.md`, workflow checkpoint files.

## Decisions and Assumptions

- A provider is carried by each immutable `ApplicationConfigurationSnapshot`; no custom static provider registry is used. An omitted provider creates a per-tracer official `NoopTracerProvider`.
- An active valid Framework span is the parent. A persisted `TelemetryContext` is used only when no valid active span exists; trace flags and tracestate are preserved.
- Only Specification 100 allowlisted scalar attributes are accepted. `blackops.result` is restricted to the finite documented values. Throwable messages, stack traces, payloads, actor/tenant identifiers, and `recordException()` are not emitted.
- Telemetry failures are swallowed at the adapter boundary and cannot replace the primary lifecycle failure.

## Commands and Results

- Orchestrator exact runtime／PostgreSQL evidence — passed: 117 tests, 837 assertions; existing PHPUnit deprecation 1 and notices 6.
- Orchestrator Task-focused suite including Application composition — passed: 480 tests, 2,000 assertions; existing deprecation 1, PHPUnit deprecations 2, notices 9.
- Full PHPUnit after final corrections — passed: 2,147 tests, 8,872 assertions; existing deprecation 1, PHPUnit deprecations 2, notices 9.
- `bash tests/Consumer/quickstart-e2e.sh` — passed: `Quickstart consumer E2E passed.`
- `bash tests/Consumer/framework-package-export.sh` — passed before commit and again from committed `87c444d`; Git and Composer package exports include the new Telemetry adapter and provider composition.
- `composer validate --strict` and locked production `composer audit --no-dev` — passed; no advisories.
- `mago format --check src tests` — passed.
- Changed Production source `mago analyze` and `tests/Internal/Telemetry/RecordingTracerProvider.php` analyze — passed: `INFO No issues found.`
- Changed Production source `mago lint` — no errors or warnings after correction; three existing help messages remain in changed scheduling／relay branches.
- Broad `mago analyze` — 24 existing mixed-assignment warnings, no errors. Broad `mago lint` — existing baseline 7 errors, 25 warnings, 29 notes, 15 help messages; no P20-018C changed-source error remains.
- `vendor/bin/deptrac` — blocked by the existing PHP 8.5 parser failure: `unexpected token "("` in `NikicFileReferenceVisitor.php:106`.
- Management-ID guard and `git diff --check` — passed.

## Span / Parent Evidence

| Boundary | Evidence | Result |
| --- | --- | --- |
| HTTP Deferred Producer success/rejection | `PostgreSqlDeferredAcceptanceOrchestratorTest::testHttpAcceptorStoresProxySubclassMessageAndJournalUsingOriginalMetadata`; `::testHttpAcceptorRecordsRejectedProducerWhenOrchestratorRejects` | `blackops.operation.accept`, Producer, masked actor/tenant, persisted encrypted Producer context, `completed`/`rejected`, ended |
| Scheduled Deferred Producer | `ScheduledOperationRuntimeTest::testDeferredProducerNestsUnderScheduleEvaluationAndPersistsItsContext` | evaluate Internal → accept Producer parent; callback sees active Producer, finite result, ended |
| Transactional Outbox Producer | `TransactionalOutboxRuntimeTest::testCommitPersistsRegistrationAndBuildsParentChildContext` | typed child context (operation type/strategy/ID) captured before insert, trace parent/result, ended |
| Worker Consumer | `DeferredWorkerRuntimeTest::testWorkerRunsClaimedOperationToCompletion` | execute Consumer, worker runtime kind, terminal result, ended |
| Relay/Schedule/Maintenance/Replay | `OutboxRelayRuntimeTest::testRetryBackoffDeadLetterFingerprintAndBatchIsolationAreSafe`; `ScheduledOperationRunnerTest::testRecordsSchedulerRuntimeSpanAroundEvaluation`; `MaintenanceSchedulerTest::testRecordsMaintenanceRuntimeSpanUntilTasksFinish`; `ObserverReplayRuntimeFailureTest::testSuccessfulReplayPreservesOriginalCorrelationDuringReplaySpan` | exact runtime name/kind/result/ended; replay preserves stored original correlation |
| Structured logs | `ExecutionScopedLoggerTest::testTelemetryIsTopLevelSafeCorrelationAndNotNestedInOperation`; `::testFrameworkErrorUsesSafeClassificationAndMaskedActorCorrelation`; `::testLoggerUsesActiveRecordingSpanCorrelation` | application/framework top-level trace/span and sensitive sentinel filtering |
| At-rest protection | `PostgreSqlDeferredAcceptanceOrchestratorTest::testHttpAcceptorStoresProxySubclassMessageAndJournalUsingOriginalMetadata`; `PostgreSqlOutboxStoreTest::testPayloadAndContextAreStoredAsSeparateBopdEnvelopes` | Deferred encrypted decode and Outbox BOPD ciphertext, combined with runtime Producer capture |

## Acceptance Criteria

- [x] Official typed OpenTelemetry adapter, deterministic scope/version, and no custom reflection adapter.
- [x] Provider isolation per immutable application snapshot with no process-global registration.
- [x] Span parent/child propagation, active parent preference, flags/tracestate preservation, and producer/consumer retry separation.
- [x] Deferred HTTP Producer recording evidence covers actual accept/encode/orchestrator success and rejection, persisted Producer context, masked attributes, finite result, and end state.
- [x] Transactional Outbox Producer recording evidence covers the actual insert boundary, child trace context, finite result, and end state.
- [x] Recording fake evidence covers Worker Consumer completion, Outbox Relay retry result, Schedule evaluate, Maintenance, Observer Replay failure, and active logger correlation; all spans assert name, kind, runtime/result attributes, and ended state.
- [x] Safe attribute allowlist, finite result values, masked actor/tenant attributes, active Journal correlation, replay original-correlation, and structured logger top-level correlation are implemented and focused-tested.
- [x] `finally` detach/end and telemetry failure isolation across runtime boundaries.
- [x] Focused correction suites, independent full suite, Consumer, Composer, formatting, changed-source quality, and source guards passed.
- [x] Broad quality results were rerun and separated from the existing Mago／Deptrac baseline blockers.

## Remaining Issues

Deptrac cannot parse the current PHP 8.5 syntax in the installed toolchain. Broad Mago retains the recorded repository baseline outside the P20-018C changed-source set. No Task-scoped acceptance issue remains.

## Suggested Next Action

Commit the accepted P20-018C scope, rerun exact committed package export, then start P20-018D Metric Adapter.
