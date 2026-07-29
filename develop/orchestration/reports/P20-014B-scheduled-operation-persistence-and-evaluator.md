# P20-014B: Scheduled Operation Persistence and Evaluator Report

## Summary

Implemented the PostgreSQL schedule state/occurrence schema and forward migration, plus an internal evaluator/store slice. The evaluator uses injected PSR-20 Clock, DBAL Connection, and IdentifierFactory; it preserves the source Clock instant for occurrence/state audit timestamps while flooring only slot and cursor boundaries to UTC minutes, locks state rows, handles first evaluation, cursor-exclusive subsequent evaluation, FireOnce misfires, overlap skips, operation ID allocation, reassignment rejection, rollback-safe transactions, claimed recovery lookup, DST duplicate-local-time suppression, and safe database failure wrapping.

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlScheduleSchema.php`
- `migrations/postgresql/Version20260728133000.php`
- `src/Internal/Scheduling/ScheduleOccurrence.php`
- `src/Internal/Scheduling/ScheduleEvaluationResult.php`
- `src/Internal/Scheduling/PostgreSqlScheduleStore.php`
- `src/Internal/Scheduling/ScheduleEvaluator.php`
- `tests/Transport/PostgreSql/PostgreSqlScheduleSchemaTest.php`
- `tests/Internal/Scheduling/ScheduleEvaluatorTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-package-export.sh`
- `develop/orchestration/tasks/P20-014B-scheduled-operation-persistence-and-evaluator.md`
- `develop/orchestration/reports/P20-014B-scheduled-operation-persistence-and-evaluator.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Decisions and Assumptions

- Occurrences use `ON DELETE RESTRICT`; retention/deletion is out of scope and schedule state must not silently erase audit/recovery rows.
- DOW Sunday `7` is normalized by the P20-014A parser. DST fall-back selection derives all offsets from timezone transitions around the slot and keeps the minimum valid UTC mapping, independent of the current scan window.
- Initial state insertion uses `INSERT ... ON CONFLICT DO NOTHING`, then locks/reads the persisted row so concurrent first evaluators converge without duplicate first-slot claims.
- Clock values earlier than a persisted cursor are a no-op and never regress cursor state.
- Clock instants retain seconds and microseconds in `evaluated_at`, `created_at`, and `updated_at`; only `scheduled_at` and `cursor_at` are minute-floored.

## Persistence Contract

`schedule_states` stores schedule identity, operation type, minute cursor, and UTC timestamps. `schedule_occurrences` stores composite schedule/UTC-slot identity, safe state/category, nullable unique Operation ID, acceptance timestamp, and UTC timestamps. Minute-boundary checks, state/operation-ID checks, RESTRICT FK, recovery index, and state index are present in both helper and migration.

## Evaluation Matrix

| Case | Behavior |
| --- | --- |
| First evaluation | Current UTC minute only; cursor created at that minute |
| Later evaluation | Cursor exclusive, now inclusive; no cursor regression |
| Multiple matching slots | Older rows `skipped_misfire`, newest one candidate |
| Active claimed/accepted | Newest row `skipped_overlap` without Operation ID |
| Terminal/skipped prior rows | Do not block a new claim |
| Concurrent first insert | Conflict loser locks persisted state and evaluates persisted cursor range |
| Fall-back duplicate local time | Minimum valid UTC mapping only |

## DST Matrix

| Fixture | Evidence |
| --- | --- |
| America/New_York 2026-03-08 02:30 local gap | Polling from 06:00Z through 08:00Z creates no occurrence and advances cursor to 08:00Z |
| America/New_York 2026-11-01 01:30 local overlap | 05:30Z claims once; split polling at 06:30Z creates no second occurrence |

## Concurrency / Crash Evidence

- Two forked PostgreSQL connections evaluate the first minute behind a filesystem barrier; row locking and `ON CONFLICT DO NOTHING` converge to one occurrence and one Operation ID, with cursor and claimed recovery assertions.
- A cursor-update trigger forces a transaction error; the occurrence and cursor remain at their pre-evaluation values.
- Recovery query returns claimed occurrences in deterministic `scheduled_at` order.

## Commands and Results

- Focused migration/schema/scheduling PHPUnit: PASS (55 tests, 171 assertions), including real PostgreSQL first/no-match/cursor advancement, sub-minute Clock precision persistence, wildcard/list/range/step/DOW 7 matching, misfire/overlap/accepted matrix, reassignment, clock rollback/recovery ordering, trigger-forced transaction rollback, helper/migration SQL parity, two-connection convergence, and NY Spring Gap/Fall Back polling.
- Targeted changed-source Mago analyze: PASS (`INFO No issues found`).
- Mago format and format check: PASS.
- Console regression PHPUnit: PASS (6 tests, 89 assertions) after synchronizing Framework migration count 7 and Framework plus Application count 8.
- Full PHPUnit: PASS (1938 tests, 7730 assertions; one existing PHP 8.5 `ReflectionProperty::setAccessible()` deprecation).
- Broad Mago: FAIL on repository-existing TestCase resolution and other test analysis findings; changed-source targeted analysis passes.
- Deptrac: BLOCKED by existing `NikicFileReferenceVisitor.php` parser error.
- Framework package export: BLOCKED only because the worker's required no-commit state leaves `Version20260728133000.php` absent from `git archive HEAD`; the required migration path is listed in the export contract and can be validated from a committed clean archive.
- Management-ID guard and `git diff --check`: PASS.
- Orchestrator independent focused PHPUnit: PASS (55 tests, 171 assertions).
- Orchestrator independent full PHPUnit: PASS (1938 tests, 7730 assertions; the same existing PHP 8.5 deprecation only).
- Orchestrator independent format check, changed-source Mago analysis, Management-ID guard, and `git diff --check`: PASS.

## Acceptance Criteria

- [x] Schema helper and migration define state/occurrence columns, checks, FK, uniqueness, and indexes consistently.
- [x] Migration inventory/current-schema tests updated to seven framework migrations and schedule tables.
- [x] Cursor/first evaluation/misfire/overlap/reassignment/rollback logic implemented.
- [x] Operation ID is allocated only for claims; skip rows remain nullable.
- [x] Sub-minute Clock precision is preserved for audit timestamps while slot/cursor timestamps remain minute boundaries.
- [x] DOW Sunday normalization and transition-based DST first-UTC mapping implemented.
- [x] Public Runtime/Invocation/Actor/Transport/Journal/CLI surfaces unchanged.
- [x] Full DB-backed evaluator concurrency/recovery/DST cross-poll acceptance suite is covered by focused PostgreSQL tests.
- [x] Package-export contract requires the new migration; executable `git archive HEAD` validation remains a post-commit release gate because this Task forbids Worker commit.

## Remaining Issues

The implementation slice and migration Console regression correction are accepted. Remaining repository gates are the baseline Broad Mago findings, the Deptrac parser error, and the commit-bound package export execution; all are outside this Task's implementation scope.

## Suggested Next Action

Proceed to P20-014C for Inline／Deferred Invocation, Actor／Authorization, Transport／Journal propagation, and occurrence state transitions. Rerun package export from a committed clean archive before release.
