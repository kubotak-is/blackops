# P19-004 Transactional Outbox Persistence Report

Status: Accepted

## Summary

Implemented the bounded Transactional Outbox persistence surface. A Public `TransactionalOutbox` capability now creates a fixed deferred child Operation and Outbox Record identity from the active parent ExecutionContext, validates Framework transaction ownership and Connection instance identity, and persists the encoded child message atomically through PostgreSQL. Relay, retry, fencing, dead letter, replay, and Community Board product changes were not introduced.

## Changed Files

- `src/Outbox/TransactionalOutbox.php`
- `src/Outbox/OutboxRegistration.php`
- `src/Core/Identifier/OutboxRecordId.php`
- `src/Internal/Outbox/TransactionalOutboxRuntime.php`
- `src/Internal/Identifier/IdentifierFactory.php`
- `src/Internal/Transaction/TransactionRuntime.php`
- `src/Transport/PostgreSql/PostgreSqlOutboxRecord.php`
- `src/Transport/PostgreSql/PostgreSqlOutboxSchema.php`
- `src/Transport/PostgreSql/PostgreSqlOutboxStore.php`
- `migrations/postgresql/Version20260724010000.php`
- Runtime container composers and compiler for capability injection
- `tests/Transport/PostgreSql/PostgreSqlOutboxStoreTest.php`
- `tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php`
- `tests/Outbox/OutboxRegistrationTest.php`
- `tests/Core/Identifier/OutboxRecordIdTest.php`
- Public API、Dependency Injection、Identifier、Migration command／runner tests
- `tests/Integration/ApplicationConsoleKernelTest.php` (migration-count fixture synchronized with the new Framework migration)
- Migration and Consumer migration-count expectations
- `docs/guide/core-api.md`
- `docs/guide/execution.md`
- `docs/guide/database-and-transactions.md`
- `docs/internal/transactional-outbox.md`
- `docs/website/tests/reader-experience.test.mjs`

## Decisions and Assumptions

- Outbox records use the existing Framework schema and the dedicated `outbox_records` table.
- Claim-before state is fixed to `pending` and state version `1`; no relay columns or runtime were added.
- Child context keeps correlation, derives causation from the parent Operation ID, inherits origin／authorization actor and parent deadline, optionally overrides execution actor, and never propagates the parent Idempotency Key Hash.
- `availableAt` is a delivery timestamp and is not used as a child deadline.
- Outbox registration requires an active parent Operation scope and a `TransactionRuntime`-owned scope whose Connection object is the configured Framework Connection object. Manual or mismatched scopes fail before any insert.
- PostgreSQL errors are wrapped in the existing safe `DeferredTransportException` boundary; SQL, table, connection, payload, and credential details are not exposed in the public message.

## Public Capability／Identity Matrix

| Case | Result |
| --- | --- |
| Active Deferred parent, matching metadata/value | New Outbox Record ID and child Operation ID are generated once |
| Child context | Parent correlation; parent Operation ID as causation; actor/deadline inheritance; no Idempotency Key Hash |
| Inline child or metadata/value mismatch | Safe fail-fast before persistence |
| No active parent context | Safe fail-fast |

## Transaction Participation Matrix

| Boundary | Result |
| --- | --- |
| Framework-owned transaction, same named Connection and instance | Insert participates in current transaction |
| Nested Required scope | Existing outer scope is reused |
| Transaction outside Framework runtime | Fail-fast; no row |
| Different named Connection or same name with another instance | Fail-fast; no row |
| Insert error | Safe transport exception; caller transaction receives the failure and rolls back through `TransactionRuntime` |

## PostgreSQL Persistence／Constraint Matrix

- Primary key: `record_id` (`uuid`); `operation_id` has a unique constraint.
- Payload/context are opaque `bytea`; content type and encoding are fixed non-empty values.
- `operation_type` and `connection_name` reject empty strings; schema/state versions are positive and fixed to one for claim-before records.
- Partial pending index is `(available_at, record_id)` and no lease/retry/dead-letter columns are present.
- Migration `Version20260724010000` is additive; down removes only the Outbox index/table.
- Schema helper and migration use the same table, columns, constraints, and index.

## Direct Transport／Compatibility Evidence

- Existing `PostgreSqlDeferredOperationSender` and Direct Deferred acceptance code were not changed.
- Existing full PHPUnit suite and the expanded transaction/context matrix reached 1,825 tests with one accepted deprecation.
- The final transaction evidence includes same-Connection Nested Required commit, outer-app/inner-other top-scope rejection, manual nested begin rejection, manual commit/autocommit rejection, and no application mutation outside the owning transaction.
- Existing Idempotency and Retention tests remain included in the full run and show no new failures.

## Sensitive Evidence

- Public registration result contains only typed IDs and UTC registration time.
- No raw Idempotency Key, credential, SQL, connection parameter, or Throwable detail is stored in the Outbox record or emitted by the public exception boundary.
- Store tests verify the absence of credential/SQL columns and fixed pending state.

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php tests/Transport/PostgreSql/PostgreSqlOutboxStoreTest.php tests/Outbox/OutboxRegistrationTest.php tests/Core/Identifier/OutboxRecordIdTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php` — PASS (44 tests, 102 assertions; Orchestrator rerun)
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php tests/Transport/PostgreSql/PostgreSqlOutboxStoreTest.php tests/Outbox/OutboxRegistrationTest.php` — PASS (23 tests, 61 assertions)
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Migration/DatabaseMigrationRunnerTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php` — PASS (36 tests, 108 assertions)
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Console/DatabaseMigrationCommandTest.php` — PASS (4 tests, 26 assertions)
- `mise exec -- pnpm --dir docs/website run test` — PASS (42 tests)
- `docker compose run --rm app mago format --check src tests examples` — PASS
- `docker compose run --rm app mago lint` — PASS with existing baseline note/help only (`SapiRuntime` empty loop and compiler no-else help)
- `docker compose run --rm app mago analyze` — PASS (no issues)
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — PASS (0 violations, 3,091 allowed)
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` — PASS
- `git diff --check` — PASS
- `docker compose run --rm app vendor/bin/phpunit` — PASS (1,825 tests, 7,370 assertions, 1 accepted deprecation; Orchestrator rerun)
- `bash tests/Consumer/framework-package-export.sh` — PASS against reviewed implementation Commit `218c945`; Framework package archive includes the new PostgreSQL migration.
- `bash tests/Consumer/community-board-clean-install.sh` — PASS against reviewed implementation Commit `218c945`; 8 migrations, build, seed, generated frontend freshness, Svelte check, 43 frontend tests, production build, and HTTP journey succeeded.

## Acceptance Criteria

- [x] Public Capability／Registration／Outbox Record ID and Core API documentation synchronized.
- [x] Parent/child identity, correlation, causation, actor/deadline, and Idempotency Key non-propagation implemented.
- [x] Same Named Connection Instance and Framework-owned transaction participation enforced.
- [x] PostgreSQL table, constraints, pending state/version, migration, and schema parity implemented and tested.
- [x] Direct Transport remains unchanged; existing Idempotency／Retention paths were included in the full run.
- [x] Sensitive payload and exception boundaries avoid SQL, credentials, connection parameters, and Throwable details.
- [x] Full PHPUnit, package export, and Fresh Community Board clean install succeed after the migration-count fixture was synchronized.
- [x] Relay／Retry／Dead Letter／Replay／Community Board Product Journey were not changed.

## Remaining Issues

None. GitHub Actions verification follows the accepted local closeout.

## Suggested Next Action

Push the accepted closeout and verify GitHub CI／Documentation Delivery. After final HEAD succeeds, prepare the P19-005 Relay Runtime and CLI Task Packet.
