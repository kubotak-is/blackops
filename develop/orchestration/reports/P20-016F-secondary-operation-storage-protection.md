# P20-016F Completion Report

## Summary

Implemented mandatory BOPD protection for PostgreSQL secondary operation storage. Outbox payload and context now use separate purposes and row-bound AAD, dead-letter reason details are stored as one protected projection, and idempotency response/result projections are protected with canonical scope, operation, schema, and tenant metadata. Claims validate decoded context against clear row identity and subject metadata. The migration refuses non-empty legacy tables before any schema change.

## Changed Files

- src/Internal/Idempotency/**
- src/Internal/Outbox/**
- src/Internal/Application/**
- src/Internal/Execution/InlineDispatcher.php
- src/Internal/Execution/DeferredAcceptanceOrchestrator.php
- src/Internal/Diagnostics/OperationDiagnosticsQuery.php
- src/Transport/PostgreSql/PostgreSqlOutbox*.php
- src/Transport/PostgreSql/PostgreSqlDeadLetter*.php
- src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php
- src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php
- src/Transport/PostgreSql/PostgreSqlDiagnosticsReader.php
- src/Transport/PostgreSql/PostgreSqlIdempotencySchema.php
- migrations/postgresql/Version20260808010000.php
- Corresponding PostgreSQL, idempotency, outbox, diagnostics, retention, worker, migration, HTTP, console, and scheduler tests
- develop/TODO.md, develop/STATE.md

## Decisions and Assumptions

- Outbox AAD record identity is the exact record_id; payload and context use distinct purposes.
- Dead-letter diagnostics expose only a protected marker and never decode the reason envelope.
- Terminal idempotency rows may carry response, result, both, or neither projection; processing rows carry neither.
- Operation type and application schema version are mandatory claim metadata and restricted clear columns.
- Existing non-empty plaintext rows are not converted or deleted; the migration fails before ALTER statements.

## Commands and Results

- Focused PHPUnit suites (split to preserve complete output): PASS, 116 tests / 620 assertions for idempotency, outbox, migration, relay, console, scheduler, and HTTP paths; and 118 tests / 869 assertions for dead-letter, retention, status, sender, worker, diagnostics, and HTTP paths (234 tests / 1,489 assertions total).
- Secondary migration guard and empty-schema parity checks: PASS, 2 tests / 43 assertions (included in the focused totals).
- Expanded canonical identity/tenant/origin/row/tag/unknown-key tamper matrix: PASS, including testWrongTenantAndOperationRowIdentityFailClosed, testWrongRowAndValidContextTenantOrOriginMismatchesRollBackLease, and testProjectionTagTamperAndUnknownKeyFailClosed.
- Idempotency retention plan/purge with undecodable BOPD response/result: PASS in testIdempotencyRetentionPlansAndPurgesUndecodableBopdProjections.
- Migration command and application-kernel inventory suites were rerun after adding Version20260808010000.
- `docker compose run --rm app composer validate --strict`: PASS.
- PHP syntax spot-check for the new migration and PostgreSQL idempotency/outbox stores: PASS.
- Changed-source `mago lint` and `mago analyze` for the modified production files (excluding the pre-existing InlineDispatcher halstead warning): PASS, no issues found. InlineDispatcher lint still reports its existing dispatchEnvelope halstead warning; it was not refactored for this task. The idempotency projection decode now validates an associative string-key projection before strict version/rejection parsing; response/result encoding was split into focused helpers to clear halstead/no-else findings, and obsolete literal/expect findings were removed. Outbox subject parsing is explicitly typed.
- Broad `mago analyze`: PASS with 0 errors and 25 warnings, all outside the corrected idempotency/outbox sources; broad lint remains an Orchestrator-level check.
- `docker compose run --rm app mago format --check src tests`: PASS.
- Management-comment guard and git diff --check: PASS.
- Orchestrator independent full `docker compose run --rm app vendor/bin/phpunit`: PASS, 2,086 tests / 8,402 assertions, with one existing deprecation.
- Orchestrator independent broad `mago lint`: existing repository baseline only, 81 findings / 9 errors; the count improved from the preceding 82-finding baseline and changed-source lint has no new issue.
- `docker compose run --rm app vendor/bin/deptrac`: unable to evaluate the graph because the installed Deptrac parser fails on PHP 8.5 at `vendor/deptrac/deptrac/src/DefaultBehavior/Ast/Parser/Helpers/NikicFileReferenceVisitor.php:106`; this is the unchanged vendor blocker, not a source dependency violation. Full PHPUnit includes the public API architecture guard.
- `bash tests/Consumer/quickstart-e2e.sh`: PASS from a fresh consumer install through 12 migrations, build, seed, HTTP, deferred retry/completion, masked diagnostics, retention, and frontend checks.
- Exact `bash tests/Consumer/framework-package-export.sh`: expected pre-commit stop because `git archive HEAD` cannot contain the untracked `Version20260808010000.php`. The same command created the Composer archive before stopping.
- Working-tree Composer archive isolation check: PASS; `Version20260808010000.php`, allowed root inventory, exclusion contract, strict Composer validation, production classmap autoload, worktree preservation, and cleanup all passed.
- Post-commit exact `bash tests/Consumer/framework-package-export.sh`: PASS after commit `02a561c`; both Git and Composer archives include `Version20260808010000.php` and pass root inventory, exclusion, strict validation, production autoload, worktree-preservation, and cleanup checks.

## Acceptance Criteria

- BOPD prefixes and plaintext absence are asserted for outbox and idempotency projections.
- Ciphertext purpose/field swaps, row/scope/operation/tenant mutations, tag tamper, and unknown key fail closed without sensitive error details; decoded outbox context is checked against operation, tenant, and origin metadata.
- Dead-letter retention/status/diagnostics fixtures use BOPD bytes and do not decode protected reason details.
- Migration runner/console/kernel/package inventory and counts include Version20260808010000; independent non-empty guards preserve row bytes, old columns, and constraints, while empty upgrades assert new columns and constraint parity.

## Remaining Issues

- Deptrac remains blocked by its installed PHP 8.5-incompatible vendor parser at `NikicFileReferenceVisitor.php:106`.
- P20-016F was committed as `02a561c` after acceptance. No push or deploy was performed.

## Suggested Next Action

Start P20-016G storage-key rotation from the clean post-package-export checkpoint.

## Orchestrator Acceptance

Accepted at 2026-08-08T16:42:58+09:00 after independent scope, DB-wire, migration-guard, tamper, non-decode, full-suite, consumer, and package review. The worker made no commit, push, or deploy.
