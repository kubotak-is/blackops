# P20-016C PostgreSQL Tenant Isolation — Completion Report

## Summary

Implemented restricted clear tenant metadata across PostgreSQL operation-owned schemas, tenant/origin pair constraints and identity indexes, v2 tenant-aware idempotency scope hashing, Deferred/Outbox tenant and origin carriers, clear-column Status subject projection, tenant-scoped Journal/Outcome reads, retention planning/deletion evidence propagation, and scheduled occurrence tenant storage. Added forward migration `Version20260803000000` with an empty-legacy-table guard; no previous migration was rewritten.

## Changed Files

See the working-tree diff. Changes are limited to the Task Packet allow-list, including PostgreSQL Transport schemas/stores, Internal Idempotency/Execution/Scheduling/Retention, Core carrier files approved by Scope Corrections, migration/package inventory, tests/report/state files.

Post-commit correction files: `.gitattributes`, `composer.json`, `tests/Consumer/framework-package-export.sh`, Task/Report/TODO/STATE synchronization.

## Decisions and Assumptions

- Tenant and origin pairs remain both-null or both-present; no legacy inference or payload decode is used for subject reads.
- Outbox rows carry both tenant and origin-actor clear pairs; schema helper and forward migration add their columns, constraints, indexes, and non-empty legacy-shape guards.
- Idempotency scope is version 2 and includes explicit tenant presence, type, and id fields.
- Replay checkpoints/audits do not receive raw tenant columns; Journal row tenant is selected by bounded predicates.
- Existing non-empty legacy tables lacking `tenant_type` are rejected by migration guards. Current-shape tables and empty legacy tables remain idempotent.

## Commands and Results

- Focused PHPUnit clear-subject/migration suite: PASS (46 tests, 207 assertions).
- Task-scoped PostgreSQL/tenant suite (`tests/Transport/PostgreSql`, Internal Execution/Idempotency/Outbox/Scheduling/Retention/Replay/Status): PASS (404 tests, 1907 assertions, 1 existing deprecation).
- Additional diagnostics/retention/replay/deferred-worker regression suite: PASS (58 tests, 495 assertions).
- Dedicated `PostgreSqlTenantIsolationTest`: PASS (10 tests, 91 assertions): actual seven-migration legacy chain plus Version20260803000000 empty-schema 9/3 constraint evidence, non-empty guard rollback with unchanged row/encoded bytes, explicit both-null acceptance and tenant/origin partial/empty rejection matrix, wrong-tenant Journal/Outcome no-decode, Idempotency/Outbox, tenant-scoped retention planning/deletion/audit rows, time-bounded Observer Replay tenant carriers and tampered clear-subject rejection, retention/schedule carriers, deterministic same-ID tenant-A/tenant-B sender safety with raw-ID-free failure, and two open transaction lease `FOR UPDATE SKIP LOCKED` claims preserving A/B tenants.
- Full PHPUnit: PASS (2043 tests, 8180 assertions, 1 existing deprecation).
- `mago format` on all changed source/migration files: PASS.
- Limited changed-production-file `mago lint` (RetentionHoldStore, RetentionPlanner, OutcomeStore, DeferredOperationMessageCodec, OutboxStore, DeferredAcceptanceOrchestrator, StatusReader): PASS (no issues).
- Changed-source `mago analyze` for PostgreSqlTenantMetadata and six schema consumers: PASS (no issues).
- Broad `mago analyze`: PASS with 17 warnings and zero errors (mixed DBAL row assignments, existing StatusReader comparisons, and Jsonl encoder generic-object access; no invalid-return errors).
- `mago format --check src tests`: PASS.
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php`: PASS (15 tests, 15 assertions).
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Migration/DatabaseMigrationRunnerTest.php tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php`: PASS after synchronizing migration count/list and outbox origin shape fixtures.
- Orchestrator `docker compose run --rm app composer validate --strict`: PASS.
- Orchestrator `docker compose run --rm app vendor/bin/phpunit`: PASS (2043 tests, 8180 assertions, 1 existing deprecation).
- Orchestrator dedicated `PostgreSqlTenantIsolationTest`: PASS (10 tests, 91 assertions).
- Orchestrator `docker compose run --rm app mago format --check src tests`: PASS.
- Orchestrator changed-production `mago lint`: PASS (no issues).
- Orchestrator broad `mago lint`: repository baseline FAIL (83 findings: 9 errors, 28 warnings, 29 notes, 17 help); no task-diff error remains.
- Orchestrator broad `mago analyze`: PASS with 17 warnings and zero errors.
- Orchestrator `vendor/bin/deptrac`: repository tooling FAIL at `vendor/deptrac/deptrac/src/DefaultBehavior/Ast/Parser/Helpers/NikicFileReferenceVisitor.php:106` under PHP 8.5.
- The earlier pre-commit archive limitation is resolved by accepted commit `3671ca0`; the exact post-commit export now passes.
- `! rg -n 'convert_from\\([^)]*encoded_(record|payload|context)' src tests`: PASS (zero matches, including Consumer shell journeys).
- Management-ID guard, Consumer encoded-field guard, touched Consumer shell syntax, and `git diff --check`: PASS.
- Consumer SQL uses clear columns; no test or journey decodes or inspects restricted blobs in SQL.
- Post-commit package correction excluded `/.claude` from both `.gitattributes` and `composer.json` archive rules and synchronized the Consumer excluded-path contract. Exact `bash tests/Consumer/framework-package-export.sh`: PASS for both Git and Composer archives, including `Version20260803000000.php` and the allowed root inventory.
- Post-correction shell syntax, encoded-field guard, management-ID guard, and `git diff --check`: PASS.
- Post-correction `docker compose run --rm app mago format --check src tests`: PASS.
- Standalone `docker compose run --rm app composer validate --strict`: PASS after retry; package-export validation also passed Composer validation for both extracted archives.

## Acceptance Criteria

- PASS: all nine operation-owned table tenant pairs, three origin-actor pairs, exact constraints, and tenant identity indexes are synchronized between helpers and the forward migration.
- PASS: fresh/current/dry-run migration inventories are eight versions; the actual prior seven-version chain upgrades when empty and rolls back unchanged when protected legacy data is non-empty.
- PASS: database evidence covers both-null acceptance and tenant/origin partial and empty rejection.
- PASS: operation duplicate, Journal, Outcome, Idempotency, Outbox, Dead Letter, Worker, Retention, Replay, and Schedule paths preserve or predicate the row tenant without cross-row reuse.
- PASS: wrong-tenant Journal/Outcome rows are excluded before invalid encoded bytes can be decoded; same-operation tenant mismatch fails with a safe error.
- PASS: Status subject uses only restricted clear columns and the encoded-field SQL projection guard has zero matches.
- PASS: tenant-aware idempotency scope v2 and scheduled candidate/global-skip behavior are covered.
- PASS: global/single-tenant behavior and the full suite remain green.
- PASS: Task, Report, STATE, and TODO are synchronized; the package correction is independently accepted and worker made no commit.

## Remaining Issues

- Broad Mago lint retains the repository baseline outside this Task: 83 findings including 9 errors. Task-diff error-level findings are clean.
- Deptrac cannot parse its own vendor visitor on the repository PHP 8.5 runtime; this is the recorded tooling baseline, not a dependency violation result.
- No remaining command blocker; the initial Docker API permission failure was transient and the retry passed.

## Suggested Next Action

Commit the accepted package-metadata correction, then proceed to P20-016E. No push or deploy was performed.
