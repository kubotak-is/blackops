# P19-006 Canonical Observer Replay Report

Status: Accepted

## Summary

Implemented the bounded PostgreSQL Canonical Journal observer replay path. Replay uses a transport-owned selector for operation, record, or UTC half-open time selection; stable keyset ordering; current sensitive projection; named observer target resolution; per-record observe/flush checkpoints; safe failure fingerprints; and a `journal:observer:replay` BlackOps CLI with dry-run, confirm, checkpoint, and persisted resume metadata. Canonical journal rows are selected only and JSONL envelopes now expose `recordId`.

## Changed Files

- `src/Internal/Replay/**`
- `src/Internal/Console/JournalObserverReplayCommand.php`, `FrameworkCommandNames.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`, `ApplicationConsoleCommandFactory.php`, `ApplicationJournalObservations.php`
- `src/Internal/Journal/JournalObserverAggregator.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplay*.php`, `PostgreSqlJournalSchema.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRequest.php`, `Binding.php`, `Loaded.php`, `Identity.php`, `SelectionQuery.php`, `CheckpointWriter.php`, `AuditWriter.php`
- `src/Internal/Console/JournalObserverReplayOptions.php`, `src/Internal/Replay/ObserverReplayRequest.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- `migrations/postgresql/Version20260724110000.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`, `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `docs/guide/observer-replay.md`, `docs/guide/project-cli.md`
- `docs/website/content-map.mjs`, `docs/website/site-navigation.mjs`, `docs/website/scripts/check-site.mjs`, `docs/website/tests/site-navigation.test.mjs`
- this report and worker checkpoint in `develop/STATE.md`

## Decisions and Assumptions

- Replay is at-least-once; observer-side `recordId` idempotency remains responsible for duplicate suppression.
- The transport selector keeps record/time/checkpoint query details out of the public `CanonicalJournalReader` contract.
- Checkpoint ownership uses a PostgreSQL advisory lock and leaves a running checkpoint resumable after process loss.
- Observer targets are normalized to stable sorted names before binding and audit hashing.

## Selection / Ordering Matrix

| Selector | Query / order |
| --- | --- |
| Operation | `operation_id`, `(sequence, record_id)` keyset |
| Record | exact `record_id` |
| Time | `[from,to)` UTC, `(occurred_at, record_id)` keyset |

## Target / Projection / Identity Matrix

Named configured bindings are validated before selection. Every selected canonical row is projected immediately with `ObservedJournalRecordProjector`/`SensitiveProjectionFilter`; `recordId`, operation identity, sequence, and occurred time are unchanged. JSONL output includes the canonical `recordId`.

## Checkpoint / Resume / Concurrency Matrix

Checkpoint rows bind selector and target hashes and persist safe selector fields, target names, cursor, counts, state, and update time. Each record advances only after all selected observers observe and flush successfully. PostgreSQL advisory locking rejects overlapping owners; interrupted `running` rows remain resumable.

## Audit / Sensitive Evidence

Audit stores selector/target hashes, actor, reason, state, timestamps, counts, and versioned failure fingerprint derived from domain separator plus Throwable class. Canonical payload, projection values, actor IDs, credentials, SQL, and throwable detail are not persisted.

## Canonical Immutability Evidence

Replay selection reads `encoded_record` only. No replay path writes the canonical journal table or appends lifecycle records.

## Commands and Results

- `mago format --check src tests` — PASS.
- Focused JSONL and observer aggregation PHPUnit — PASS (8 tests, 29 assertions).
- Canonical PostgreSQL journal migration/read-only store suite — PASS (11 tests, 56 assertions).
- Replay PostgreSQL store integration — PASS (10 tests, 22 assertions): UTC selector normalization/half-open validation, finite operation/record selection with keyset cursor, persisted selector/target resume metadata, concurrent checkpoint lock refusal, stale-running recovery after owner session close, checkpoint mismatch rejection, audit terminal/count/first/last safe fields, corrupt-row safe failure, and canonical row-count preservation.
- Migration runner/schema synchronization — PASS (19 tests, 79 assertions), including class-specific historical Outbox down lookup and additive replay table expectations.
- Canonical byte/ID immutability — PASS in replay PG suite (11 tests, 23 assertions).
- Target validation unit — PASS (1 assertion).
- Runtime observe failure integration — PASS (1 test, 4 assertions): first record remains checkpointed while the failing second record is unadvanced.
- Runtime flush/crash-window integration — PASS (2 tests, 6 assertions): flush failure preserves prior cursor and resume redelivers identical record ID into an idempotent target.
- CLI exact-one validation — PASS (1 test, 1 assertion): conflicting dry-run/confirm flags reject before database query.
- CLI dry-run/confirm/resume integration — PASS (3 tests, 6 assertions), including safe output, checkpoint output, persisted selector/target resume, and audit rows.
- Correction pass: normalized target binding, microsecond selector hashing, and invocation-specific audit IDs; latest focused subset passed 17 tests/39 assertions.
- Ownership correction: advance/finish enforce exactly-one checkpoint mutation and runtime no longer double-finalizes a completed checkpoint; replay/runtime subset passes 13 tests/29 assertions.
- Transaction safety correction: finish/fail now update checkpoint and the exact invocation audit ID in one transaction with row-count ownership checks; rollback failures and advisory unlock failures cannot mask a primary observer failure. Replay selection is inside the guarded runtime scope so corrupt canonical rows also terminalize safely and release the lock.
- Schema parity correction: helper and additive migration now define the same selector-shape, target-array, hash, and versioned fingerprint constraints; audit rows persist target names. Migration up/down coverage now checks replay columns/constraints and verifies down removes only the replay index/tables.
- Observer bootstrap correction — PASS (38 tests, 159 assertions across configuration, CLI, and kernel): normal journal observer remains eager/fail-fast; replay targets use lazy no-touch construction.
- Documentation/package sync — Added Observer Replay guide, Unreleased changelog entry, package-export migration inclusion, and Community Board clean-install expectation 10.
- CLI strict bounds/safe output — PASS (4 tests, 9 assertions), including batch bounds and omission of actor/payload text.
- Application kernel list/help/collision — PASS (18 tests, 97 assertions), including replay metadata/options and reserved-name collision.
- Configured JSONL no-touch kernel test — PASS (19 tests, 101 assertions); positive record/time selector CLI executions — PASS (4 tests, 10 assertions).
- Combined focused P19-006 suite — PASS (59 tests, 239 assertions): CLI, runtime observe/flush, PostgreSQL selectors/checkpoints/audits/immutability, migration up/down expectations, JSONL, and application kernel.
- Latest focused replay/runtime/CLI/migration suite — PASS (38 tests, 125 assertions) after transaction, exact-audit, lock-release, and schema parity corrections.
- An earlier combined quality command hit the sandbox Docker socket permission; the required gates were rerun with Docker access and passed individually below.
- `mago analyze` — PASS (`INFO No issues found`). Full PHPUnit, package export, consumer clean install, and documentation website gates remain for Orchestrator review.
- Deptrac — PASS (0 violations).
- Quality correction: typed begin/load/request DTOs removed mixed array access; option parsing now uses typed validators; replay finalization no longer throws or returns from `finally`; selector/cursor/target helpers remove nullable access, nested ternary, blockless `if`, empty catches, and unused imports. Selection SQL and checkpoint/audit persistence responsibilities were split into dedicated helpers.
- `docker compose run --rm app mago format --check src tests` — PASS (all files already formatted).
- `docker compose run --rm app mago lint` — PASS (exit 0; only pre-existing `SapiRuntime` empty-loop note and unrelated no-else help remain).
- Focused replay/runtime/CLI/PostgreSQL suite — PASS (18 tests, 40 assertions).
- Focused migration suite — PASS (23 tests, 88 assertions).
- Focused ApplicationConsoleKernel suite — PASS (19 tests, 101 assertions).
- Focused JSONL/configuration suite — PASS (31 tests, 92 assertions).
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — PASS (0 violations, 0 errors).
- `git diff --check` — PASS.
- Contract correction: checkpoint IDs now use segmented lowercase grammar (`^[a-z0-9]+(?:[._-][a-z0-9]+)*$`); exact-record selection honors a persisted cursor; target hashes use sorted canonical JSON; primary observer failures remain the safe surfaced error when failure-audit persistence fails.
- Audit correction: each `advance` atomically updates checkpoint cumulative counters and the exact invocation audit counters; terminalization no longer copies cumulative checkpoint totals. Audit rows now include safe selector operation/record/time boundaries with the same shape constraint as checkpoints.
- Regression evidence: checkpoint grammar, exact-record cursor, collision-safe target binding, per-invocation counts, audit selector boundary, and audit-persistence failure tests are covered in the focused suite (22 tests, 50 assertions).
- Final safety correction: checkpoint validation now rejects UTF-8 byte lengths over 128 in runtime and store identity validation, with 128-byte acceptance and 129-byte rejection coverage. Observer failures always surface the fixed safe message `Observer replay delivery failed.` without raw Throwable message/trace or SQL/audit persistence detail; the original Throwable class is still used for the persisted fingerprint.
- Final focused rerun — PASS (22 tests, 54 assertions).
- Final `mago format --check src tests` — PASS; `mago analyze` — PASS (`INFO No issues found`); `mago lint` — PASS (exit 0, unrelated baseline note/help only); management-ID and `git diff --check` — PASS.
- Frozen-gate migration expectation sync — PASS (`ApplicationConsoleKernelTest` and `DatabaseMigrationCommandTest`, 6 tests, 89 assertions); pending/applied migration counts now include the additive replay migration consistently.
- Observer Replay documentation sync — PASS (`mise exec -- pnpm --dir docs/website run test`, 42 tests); `mise exec -- pnpm --dir docs/website run build` generated the public `reference/observer-replay` page. Guide is Japanese, carries content-map metadata, appears in the Reference sidebar, and is linked from `project-cli.md` under the BlackOps CLI terminology.
- Final static rerun — PASS (`mago format --check src tests`; `mago analyze` reports no issues; `mago lint` exit 0 with only unrelated baseline note/help); management-ID guard and `git diff --check` — PASS.
- Website frozen rerun — PASS after adding `/reference/observer-replay/` to the static route fixture used by `check-site.mjs`; docs test 42/42, docs build generated 32 public pages and Pagefind/site checks passed. Mago format check and management-ID/`git diff --check` guards remain PASS.
- Orchestrator frozen Full PHPUnit — PASS (1,875 tests, 7,576 assertions, 1 accepted deprecation).
- Orchestrator frozen quality gates — PASS: Mago format/check, Mago analyze (`No issues found`), Mago lint exit 0 with only unrelated baseline note/help, and Deptrac 0 violations.
- Orchestrator frozen documentation gates — PASS: 42/42 reader tests and a 32-page website build including `/reference/observer-replay/`, artifact validation, navigation, accessibility, and Pagefind checks.
- `bash tests/Consumer/framework-package-export.sh` against implementation commit `4bab9ac` — PASS.
- `bash tests/Consumer/community-board-clean-install.sh` against implementation commit `4bab9ac` — PASS: 10 migrations, application build, frontend generation/fresh check, Svelte check, 43 frontend tests, production build, database snapshot, and HTTP journey.
- GitHub Actions CI Run `30073464604` — PASS: Documentation Website, Mago/PHPUnit/Deptrac, Frontend Contract/Runtime, Community Board Clean Install/Seed, and Full-stack Product Journey all succeeded.
- Documentation Delivery Run `30073464621` — PASS: verified artifact build and delivery workflow succeeded; production deploy remained skipped by the existing credential gate.

## Acceptance Criteria

- [x] Selector, bounded batch, stable keyset ordering and transport-only replay reader.
- [x] Current projection and canonical record identity preservation.
- [x] JSONL `recordId` envelope field.
- [x] Additive checkpoint/audit migration and schema helper additions.
- [x] Real PostgreSQL selector/checkpoint/audit/failure/flush/resume matrix and direct migration up/down parity tests are covered by focused suites.
- [x] Full PHPUnit, Mago, Deptrac, and documentation website gates pass under Orchestrator verification.
- [x] Framework package export and fresh Community Board clean install pass against committed HEAD `4bab9ac`.

## Remaining Issues

None.

## Suggested Next Action

Close the implementation and acceptance commits in GitHub Actions, then start P19-007 Community Board Reliability Journey.
