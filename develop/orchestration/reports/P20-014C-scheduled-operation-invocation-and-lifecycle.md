# P20-014C Scheduled Operation Invocation and Lifecycle

## Summary

Scheduled occurrences now enter the existing Inline and Deferred lifecycle with their fixed occurrence Operation ID, correlation ID, first evaluation instant, and safe Schedule Context. Actor resolution is application-owned through `ScheduledActorProvider`; the execution actor remains framework-fixed. Scheduled values are constructorless-built and validated before authorization and execution.

## Changed Files

- `src/Scheduling/ScheduledActorProvider.php` and `src/Internal/Scheduling/**`
- Scheduled ExecutionContext, codec, Journal operation/builder/projection, and PostgreSQL Journal codec changes
- Inline scheduled seam and Deferred acceptance/worker/supervision/recovery occurrence hooks
- Deptrac Scheduling layer/ruleset and namespace architecture test
- Focused codec, actor/envelope, lifecycle, public-contract, and PostgreSQL transition tests
- `develop/spec/16-namespace-dependencies.md`, `develop/spec/README.md`, `develop/TODO.md`, `develop/STATE.md`

## Decisions and Assumptions

- `ScheduledActorProvider::actor()` may explicitly return `null`; no anonymous fallback is created. Authorized operations without a provider fail before invocation.
- `ScheduledRuntimeActor::ref()` is always the execution actor (`scheduled-runtime`, `system`). Provider actors are copied only to origin/authorization.
- Schedule Context contains only name, canonical UTC microsecond `scheduled_at`, and IANA timezone. Legacy payloads without `schedule` decode to `null`.
- Occurrence transitions are guarded by Operation ID and expected state. Claimed occurrences may become accepted/completed/rejected/failed; accepted occurrences may remain accepted for retry or become completed/rejected/failed/dead_lettered.
- P20-014D composition must explicitly inject the same PSR-20 clock into Journal/runtime/lifecycle components where a shared transaction timestamp is required.

## Actor Matrix

| Case | Origin | Authorization | Execution | Result |
| --- | --- | --- | --- | --- |
| Provider actor | Provider actor | Provider actor | Fixed scheduled runtime actor | Authorized boundary evaluates normally |
| Provider returns null | null | null | Fixed scheduled runtime actor | Authorization may reject authentication-required policy |
| Authorized operation without provider | none | none | none | Safe construction failure; no anonymous fallback |

## Invocation Matrix

| Strategy | Entry | Validation | Persistence |
| --- | --- | --- | --- |
| Inline | `InlineDispatcher::dispatchScheduled` | Validator before lifecycle | received/rejected or normal attempt Journal; occurrence terminal transition |
| Deferred | `DeferredAcceptanceOrchestrator::accept` | Validator inside acceptance transaction | received/accepted/rejected/failed, transport and occurrence transition in same DB transaction |
| Deferred worker | Existing runtime/supervisor | Existing worker validation/authorization | completed/rejected/retry/failed/dead-letter occurrence transition in worker transaction |

## Occurrence Transition Matrix

| Trigger | From | To | Category |
| --- | --- | --- | --- |
| Deferred acceptance acknowledgement | claimed | accepted | none |
| Inline completion | claimed | completed | none |
| Validation/authorization rejection | claimed | rejected | `validation_failed` or rejection code |
| Value/actor/acceptance/inline failure | claimed | failed | stable `scheduled_*`/`*_failed` category |
| Worker completion/rejection/failure/dead-letter | accepted | completed/rejected/failed/dead_lettered | stable category only |
| Retry scheduling | accepted | accepted | none |

All updates require exactly one row. Categories reject unsafe content and never contain exception messages, SQL, actor identifiers, or credentials. Timestamps preserve UTC microseconds.

## Transport / Journal Shape

Execution Context and Journal operation payloads carry an optional Schedule Context object with `name`, `scheduled_at`, and `timezone`. Observed projection preserves that object while masking actors. Missing schedule fields remain backward-compatible for legacy HTTP/Console/child payloads.

## Transaction / Recovery Evidence

- PostgreSQL lifecycle tests cover accepted acknowledgement precision, all accepted terminal/retry transitions, expected-state zero-row rejection, terminal reopen rejection, and outer transaction rollback.
- Deferred acceptance/worker/supervision hooks call lifecycle transitions before their surrounding transaction returns, so occurrence state rolls back with operations/journal on failure. PostgreSQL tests cover scheduled acceptance, worker completion, dead-letter, retry recovery, and Schedule Context preservation in Journal records.
- Acceptance rollback trigger evidence: forcing only `claimed -> accepted` to fail rolls back transport operation and accepted Journal rows; the safe compensation transaction records only received/failed and moves the occurrence to `failed`.
- Worker rollback trigger evidence: forcing `accepted -> completed` to fail leaves the occurrence `accepted`, no completed operation state/Journal event, and no outcome row.
- `ScheduledOperationRuntimeTest` exercises both `invoke()` strategy branches, fixed envelope identity/context, constructor failure clock/category, and actor-provider failure safe category through recording seams and PostgreSQL occurrence state.
- Lease-expired recovery reconstructs idempotency hash and Schedule Context before retry/failure/dead-letter handling, preserving the fixed operation identity.
- Inline terminal failures use the injected PSR-20 clock; validation rejection uses the rejection Journal record instant.

## Commands and Results

- Focused PHPUnit command: **PASS**, 210 tests / 1,014 assertions; one pre-existing PHP 8.5 `ReflectionProperty::setAccessible()` deprecation. Runtime seam tests complete without PHPUnit notices.
- Full PHPUnit latest rerun: **PASS**, 1,971 tests / 7,856 assertions; one pre-existing PHP 8.5 `ReflectionProperty::setAccessible()` deprecation. An earlier run had the intermittent existing Outbox heartbeat failure (`OutboxRelayRuntimeTest::testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection`); the isolated Outbox test passes (4 tests / 22 assertions), and this rerun passed.
- `mago format --check src tests`: **PASS**.
- Changed-source `mago analyze`: **PASS**, no issues found.
- `mago analyze src tests`: **BLOCKED by existing repository baseline** (1,057 issues, including PHPUnit `TestCase` resolution and unrelated test warnings).
- `vendor/bin/deptrac analyse --no-progress`: **BLOCKED by existing PHP 8.5 parser incompatibility** in `NikicFileReferenceVisitor.php:106`.
- ID guard (`rg` command): **PASS**.
- `git diff --check`: **PASS**.
- Orchestrator Architecture/Scheduling PHPUnit: **PASS**, 20 tests / 330 assertions.

## Orchestrator Acceptance

The Orchestrator reviewed the public actor boundary, Scheduled Runtime entry seam, safe value/actor failures, fixed root identity, Inline/Deferred validation and authorization, versioned codec and Journal projection, expected-state occurrence transitions, transaction rollback, worker retry/dead-letter, and lease recovery. Independent focused, full, format, changed-source analysis, architecture, management-ID, and diff checks pass. P20-014C is accepted without a commit.

## Acceptance Criteria

- [x] Public actor provider, fixed execution actor, provider/null/error handling
- [x] Scheduling namespace layer/ruleset and architecture evidence
- [x] Fixed scheduled root context and constructorless value construction
- [x] Inline and Deferred validation, authorization, Journal, transport, worker, retry, and terminal seams
- [x] Versioned Schedule Context codec/Journals with legacy compatibility and safe projection
- [x] Expected-state guarded occurrence transitions with safe categories and transaction rollback evidence
- [x] Lease-expired recovery preserves Schedule Context and idempotency hash
- [x] Report/STATE/TODO synchronization; no commit

## Remaining Issues

- Broad Mago analysis and Deptrac remain blocked by the repository/environment baseline described above.
- Runtime composition and CLI wiring are intentionally deferred to P20-014D and out of scope.

## Suggested Next Action

Proceed to P20-014D Application composition, one-shot BlackOps CLI command, and Consumer crash/concurrency evidence.
