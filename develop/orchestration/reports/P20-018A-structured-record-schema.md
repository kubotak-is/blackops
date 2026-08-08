# P20-018A Structured Record Schema Report

Status: Accepted

## Summary

Implemented the Structured Record v1 wire boundary for application, framework, journal, and retention audit JSONL. Application/framework records now use a canonical top-level envelope; audit records use stable `event` and safe `data`; journal projection and encoding preserve the existing lifecycle and empty-shape contract while masking identity fields.

## Changed Files

- `src/Internal/Logging/StructuredJsonlFormatter.php`
- `src/Internal/Logging/MonologJsonlLoggerFactory.php`
- `src/Internal/Logging/ExecutionScopedLogger.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Internal/Retention/LoggingRetentionPurgeAuditPort.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- Corresponding logging, projection, retention, integration, and Consumer tests
- `docs/internal/retention-purge-audit.md`, `docs/internal/monolog-jsonl-backend.md`, `docs/internal/execution-scoped-logger.md`
- `develop/spec/10-logging-and-traceability.md`, `develop/spec/94-journal-documentation.md`, `develop/spec/100-structured-logging-and-opentelemetry.md`

## Decisions and Assumptions

- `StructuredJsonlFormatter` is the only public application/framework Monolog wire formatter. It hardcodes `schemaVersion: 1` and accepts only `application`, `framework`, or `audit` kinds.
- Application/framework expose lowercase `level`, `message`, `channel`, filtered `context`, optional `operation`, and `attempt` only after an attempt starts. `operation.attemptId` is not emitted.
- Strategy is the stable `inline`/`deferred` identifier. Actor and tenant IDs are always `[masked]`; raw retention actor IDs are also masked.
- Audit JSONL exposes common `schemaVersion`, `kind`, `occurredAt`, stable `event`, and safe `data` only. Monolog implementation fields are omitted.
- Journal encoder masks identities even when called directly; projected tenant and schedule use safe type/name and UTC nominal time. Existing nullable journal attempt and empty object/list shapes remain unchanged.
- No OpenTelemetry dependency, context, span, metric, health, exporter, or collector code was added.

## Exact Wire / Redaction Evidence

- `MonologJsonlLoggerFactoryTest::testExecutionScopedLoggerWritesFilteredOperationContextToJson` asserts top-level `schemaVersion`, `kind`, lowercase `level`, `occurredAt`, `operation`, top-level `attempt`, filtered context, no `operation.attemptId`, and no `datetime`, `level_name`, or `extra`.
- `MonologJsonlLoggerFactoryTest::testContextIsAlwaysAnObjectWhileNestedListsRemainLists` and `testEmptyContextUsesAnObjectWireShape` assert object context, preserved nested/list values, and `{}` empty shape.
- `MonologJsonlLoggerFactoryTest::testOperationTenantAndScheduleAreMaskedAndUtc` asserts application tenant masking and UTC schedule projection.
- `ExecutionScopedLoggerTest::testOperationWithoutAttemptOmitsTopLevelAttempt` proves pre-attempt application scope omits `attempt`.
- `LoggingRetentionPurgeAuditPortTest::testWritesPayloadFreeOneLineJsonThroughMonologBackend` asserts audit `event`/`data`, domain `occurredAt` from `purgedAt`, no application `message`/`channel`/`level`, no Monolog fields, and no raw retention actor; `testAuditTenantIsMasked` covers non-null tenant.
- `ObservedJournalRecordProjectorTest::testEncoderProjectsScheduleWithUtcNominalTime` asserts Journal schedule name and UTC nominal timestamp.
- `JsonlJournalObserverTest::testJsonlMasksTenantAndActorIdsAtEncoderBoundary` asserts tenant and actor sentinels are absent and `[masked]` is present.
- Quickstart failure journey now correlates via top-level `operation.id` and asserts exact top-level schema, kind, filtered context, masked actor, and absent Monolog fields.

## Commands and Results

- `docker compose run --rm app composer validate --strict` — PASS.
- Focused PHPUnit (`tests/Internal/Logging tests/Logging tests/Internal/Projection tests/Internal/Retention` plus affected Integration tests) — worker PASS, 45 tests / 343 assertions; correction rerun PASS, 41 tests / 224 assertions; Orchestrator post-correction PASS, 50 tests / 367 assertions.
- `bash tests/Consumer/quickstart-e2e.sh` — PASS.
- `docker compose run --rm app vendor/bin/phpunit` — PASS, 2,110 tests / 8,687 assertions; existing 1 deprecation.
- `docker compose run --rm app mago format --check src tests` — PASS.
- Changed-source Mago lint — PASS (only non-error style help for an existing branch form); changed-source Mago analyze — PASS with no issues after refactor.
- Broad Mago lint — known repository baseline, 83 findings / 9 errors; errors are outside this Task's changed files.
- Broad Mago analyze — known repository baseline, 24 warnings; findings are outside this Task's changed files.
- `docker compose run --rm app vendor/bin/deptrac` — blocked by known PHP 8.5 parser incompatibility at vendor `NikicFileReferenceVisitor.php:106`.
- Management-ID guard and `git diff --check` — PASS.

## Acceptance Criteria

- [x] Common top-level schemaVersion/kind/occurredAt and application/framework fields.
- [x] Monolog nested context shape and implementation fields removed from public JSONL.
- [x] UTC microseconds, LF, empty object/list, operation/attempt, identity masking, safe failure, and audit wire tests.
- [x] Existing journal lifecycle, nullable attempt, and safe projection behavior preserved.
- [x] Quickstart and full PHPUnit pass.
- [x] Task, STATE, TODO, specs, and internal contracts synchronized.
- [x] No OpenTelemetry implementation or dependency added.

## Remaining Issues

- Broad Mago lint/analyze and Deptrac remain repository/environment baselines documented above.
- No active Task-scoped issue remains. No worker commit was created.

## Suggested Next Action

Commit the accepted P20-018A slice and continue with P20-018B Telemetry Context and process-boundary propagation.
