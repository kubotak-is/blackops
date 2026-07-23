# P19-003 HTTP and PHP Duplicate Lifecycle and Retention

Status: Accepted

## P19-003 Correction Round 4D (Evidence Recovery)

Added `IdempotencyRecovery` under the Internal idempotency boundary and wired it through the production runtime composers. Inline recovery validates canonical journal identity/lifecycle and reconstructs completed or rejected typed results; failed, malformed, or ambiguous evidence becomes an internal-failure marker with a safe 500 snapshot. Deferred recovery requires a matching operations row and durable `operation.accepted`, uses `operations.accepted_at` as the sole acknowledgement timestamp, and reconstructs the exact 202 acknowledgement. Recovery terminalization stores the safe response snapshot atomically; a losing CAS re-reads the winner terminal or preserves `idempotency_in_progress` when processing remains.

The PostgreSQL idempotency schema/migration and `TerminalRecord` now carry the optional deferred `accepted_at` projection with coherent processing/terminal checks. Recovery-focused tests cover inline completed/rejected/failed, missing and incomplete evidence, ambiguous evidence, deferred acceptedAt exactness, inline/deferred CAS winner replay, processing preservation, invalid deferred projection failure, invalid-evidence CAS winner/processing races, responder snapshot failure fallback, and the custom schema dependency setting: 16 tests / 52 assertions PASS. Mago lint now reports only the two pre-existing baseline findings (`SapiRuntime` note and `RuntimeContainerCompiler` help); Mago Analyze is clean.

## Summary

Connected the accepted P19-002 idempotency core to HTTP mutation handling and PHP inline/deferred dispatch. Added strict key parsing and lifecycle ordering, replay markers, typed PHP result replay, PostgreSQL atomic claim/terminalization with versioned schema and migration, evidence-based crash recovery with CAS winner handling, and independent idempotency retention/hold/plan/purge/audit integration.

## Changed Files

- Public lifecycle contracts: `src/Execution/Dispatcher.php`, `src/Core/OperationResult.php`, `src/Core/Execution/DeferredAcknowledgement.php`, HTTP acceptor/handler/responder files.
- Internal lifecycle wiring: `src/Internal/Execution/**`, `src/Internal/Http/**`, `src/Internal/Runtime/**`, `src/Internal/Application/**`, and `src/Internal/Idempotency/**`, including `IdempotencyRecovery`.
- PostgreSQL: `src/Internal/Idempotency/PostgreSqlIdempotencyStore.php`, `src/Transport/PostgreSql/PostgreSqlIdempotencySchema.php`, retention planner/purge integration, deferred schema, and `migrations/postgresql/Version20260724000000.php`.
- Retention: `src/Core/Retention/**` and retention console/runtime/configuration files.
- Tests: operation/replay, HTTP validation/replay, duplicate matrix, PostgreSQL atomic/cross-process replay, migration synchronization, and idempotency purge/hold/audit coverage.
- Documentation/specification synchronization under `docs/guide/`, `docs/internal/`, and `develop/spec/`.

## Decisions and Assumptions

- The existing three-argument `Dispatcher::dispatch()` call remains valid; the key is an optional fourth argument.
- HTTP accepts exactly one raw `Idempotency-Key` field. Empty, comma-joined, malformed, GET/HEAD, and ephemeral-route keys fail before operation ID/record creation. Authentication and authorization are evaluated before claim.
- Anonymous keyed requests return the stable rejection without consulting the store. Conflict and in-progress responses expose no actor, fingerprint, or original result.
- Inline replay uses persisted typed result data; the responder projects only framework replay headers (`Idempotency-Replayed` and `Cache-Control`). Deferred replay reconstructs the same 202 acknowledgement and operation identity. No application headers, credentials, raw key, raw values, or throwable detail are persisted.
- Versioned migration is additive (`Version20260724000000`) so earlier applied versions remain immutable. Runtime migration is explicit; schema helpers and migration metadata are synchronized.
- A processing record is never implicitly deleted after a claim/crash. Evidence-based recovery validates the canonical journal and, for deferred acceptance, the durable operations row before CAS terminalization. Insufficient but valid evidence remains processing; corrupt or contradictory evidence closes at the safe replay-failure boundary.
- Idempotency retention defaults to the longest of the existing four retention periods unless `idempotency_record_days` is explicitly configured. Holds stop both planning and purge, and audit rows contain counts/period/operation/actor/policy only.
- The active Task Packet was corrected to include `ApplicationRetentionCommandFactory.php` and `docs/guide/glossary.md`, which are required to wire the accepted CLI configuration and keep public terminology synchronized.

## Entry / Ordering Matrix

| Entry | Keyless | Keyed | Claim point |
| --- | --- | --- | --- |
| HTTP mutation | unchanged | parse -> route/authz -> scope/fingerprint -> atomic claim | after binding, validation, authentication, authorization |
| HTTP GET/HEAD or ephemeral route | unchanged | `idempotency_not_supported` | no claim |
| PHP inline dispatch | unchanged | authz -> scope/fingerprint -> claim -> execute/replay | after authorization |
| PHP deferred acceptance | unchanged | authz -> scope/fingerprint -> claim -> durable 202/replay | after authorization |

## Duplicate / Replay Matrix

| Existing record | Same fingerprint | Different fingerprint |
| --- | --- | --- |
| none | one new operation/processing record | one new operation/processing record |
| processing | `idempotency_in_progress` | `idempotency_conflict` |
| terminal with typed result/snapshot | original result/status and operation ID, replay marker | `idempotency_conflict` |
| retained record without usable evidence | `idempotency_expired` | `idempotency_conflict` |

Real Inline, HTTP, Deferred, PostgreSQL, and recovery tests cover same-fingerprint replay, conflict, processing, terminal, expired, internal-failure, cross-process, and CAS race paths without duplicate handler or enqueue execution.

## PostgreSQL Claim / Recovery Matrix

- Unique `(scope_version, scope_hash)` plus one insert transaction makes concurrent claims atomic.
- Terminalization checks operation ID, expected state, and fingerprint before updating.
- Result codec persists typed outcomes/rejections; unknown versions, malformed rows, and storage failures close at the safe internal-failure boundary.
- `IdempotencyRecovery` validates complete operation identity/lifecycle evidence, reconstructs inline typed results or deferred accepted acknowledgements, and persists the versioned safe response snapshot in the same CAS terminalization. Missing or incomplete evidence remains processing; malformed evidence never executes a handler or enqueues a message.

## Retention / Hold / Audit Matrix

- `IdempotencyRecord` is a separate retention and purge target.
- Planner excludes held records and records an idempotency target in purge audit.
- Confirmed purge deletes only terminal records past the configured cutoff; a subsequent claim can then reuse the key.
- Dry-run, confirmed purge, scheduler/runtime configuration, and migration audit constraints include the new target.

## Sensitive Evidence

Tests and codecs verify that raw key, actor credential, canonical input values, arbitrary application headers, cookies, and throwable details do not cross persistence, replay, error, or audit boundaries.

## Commands and Results

- Focused HTTP/PHP/PostgreSQL/recovery/retention suites — PASS, including recovery **16 tests / 52 assertions** and the combined duplicate lifecycle matrix.
- `docker compose run --rm app vendor/bin/phpunit` — PASS, **1,793 tests / 7,283 assertions**, with one accepted deprecation and no deleted existing test.
- `docker compose run --rm app mago format --check src tests examples` — PASS (`All files are already formatted`).
- `docker compose run --rm app mago lint` — current scope clean; only accepted baseline findings remain in `SapiRuntime` (note) and `RuntimeContainerCompiler` (help).
- `docker compose run --rm app mago analyze` — PASS, no issues found.
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — orchestrator rerun PASS, 0 violations / 3,062 allowed; `deptrac.yaml` unchanged.
- `mise exec -- pnpm --dir docs/website run test` — PASS, 42/42.
- `bash tests/Consumer/frankenphp-worker-mode.sh` — PASS, including worker reuse, journal flush, request isolation, reconnect, restart bounds, and classic fallback.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS.
- `git diff --check` — PASS.
- `git status --short` — only Task Packet-authorized files are modified or untracked before the orchestrator commit.

## Acceptance Criteria

- [x] Keyless HTTP/PHP behavior remains covered by the full suite.
- [x] Invalid, multiple, unsupported, and anonymous keys reject without operation ID or record creation.
- [x] Claim follows binding/validation/authentication/authorization.
- [x] Same/conflict/processing/terminal/expired matrices are covered by real HTTP/PHP/PostgreSQL lifecycle tests.
- [x] Replay headers, cache control, response attachment failure, and safe persistence boundaries are covered.
- [x] PostgreSQL atomic claim/terminalize, evidence-based crash recovery, integrity failure, and cross-process/CAS matrices are verified.
- [x] Independent retention, hold, plan, purge, audit, and scheduler wiring is covered.
- [x] Docs/specs and migration tests are synchronized.
- [x] Deptrac is green after the store layer correction.
- [x] No Outbox/Relay/Community Board changes were made.

## Remaining Issues

- No P19-003 blocker remains. Full Mago Lint retains only the accepted pre-existing `SapiRuntime` note and `RuntimeContainerCompiler` help finding; Full PHPUnit retains one accepted deprecation.

## Suggested Next Action

Commit and push the accepted P19-003 scope, verify GitHub Actions and Documentation Delivery, then prepare P19-004 Transactional Outbox Persistence.

## P19-003 Correction Round 3A

Added real `InlineDispatcher` + `InMemoryIdempotencyStore` lifecycle coverage in `tests/Internal/Execution/InlineDispatcherTest.php`: keyed completed and rejected replay, original operation identity, typed result/rejection, handler-once and zero duplicate journal records, fingerprint conflict, processing, expired terminal without typed result, anonymous no-record rejection, authorization exactly-once per keyed call including post-success denial, and serialized sensitive-boundary assertions. Focused result: 30 tests / 130 assertions PASS (one existing PHPUnit deprecation). HTTP, deferred, PostgreSQL concurrency, evidence-based recovery, and their broad sensitive matrices remain unimplemented/unverified and stay unchecked above.

## P19-003 Correction Round 3B

Added real POST `OperationRequestHandler` + `InlineDispatcher` + `InMemoryIdempotencyStore` replay coverage and framework snapshot allowlist assertions in `tests/Http/OperationRequestHandlerTest.php`. The focused HTTP suite passes 33 tests / 91 assertions, including same-response replay headers/body, handler-once behavior, and exclusion of Authorization/Cookie/Set-Cookie/arbitrary headers from snapshots. Deferred, PostgreSQL concurrency, attach-failure, throwable replay, and evidence-based Recovery matrices remain unimplemented/unverified.

## P19-003 Correction Round 3C

Added the missing real HTTP rejection matrix in `tests/Http/OperationRequestHandlerTest.php`. `OperationRequestHandler` now runs through the production `InlineDispatcher` and in-memory store fixture for conflict, processing, and expired records; each 409 response is asserted to omit `operationId`. A counting store decorator proves malformed empty/comma/multiple headers, keyed GET/HEAD, keyed ephemeral routes, and anonymous keyed mutation requests reject before any claim (anonymous claim count is zero). The focused HTTP suite passes 35 tests / 127 assertions. Focused Mago format, management-ID guard, and diff check pass. Deferred acceptance, PostgreSQL concurrency, and evidence-based Recovery remain intentionally unchecked.

## P19-003 Correction Round 3D

Added real HTTP internal-failure and response-attachment failure coverage in `tests/Http/OperationRequestHandlerTest.php`. A production `OperationFailureErrorBoundary` around `OperationRequestHandler` and `InlineDispatcher` now verifies that a throwing keyed handler produces a correlated safe 500, terminalizes an internal-failure result, persists the 500 snapshot, and replays the same safe body/status with the original operation ID, replay headers, and no duplicate journal records or handler invocation. A controlled store whose `attachResponse()` returns false verifies a successful keyed request closes to a correlated safe 500 without leaking the outcome, key, or exception detail and without escaping an exception. Focused HTTP PHPUnit passes 37 tests / 153 assertions; full Mago Analyze passes with no issues; focused Mago Analyze path invocation retains the repository's PHPUnit dependency baseline. Focused format, management-ID guard, and diff check pass. Deferred acceptance, PostgreSQL concurrency, and evidence-based Recovery remain intentionally unchecked.

## P19-003 Correction Round 4A

Added real keyed Deferred HTTP acceptance coverage in `tests/Http/DeferredOperationRequestHandlerTest.php` using `DeferredAcceptanceOrchestrator`, `PostgreSqlDeferredOperationSender`, PostgreSQL journal, and `InMemoryIdempotencyStore`. The suite proves durable 202 replay with identical body/status/operation ID/acceptedAt and replay headers, one operation/enqueue row, zero duplicate journal records, safe snapshot allowlist, fingerprint conflict and processing 409 responses without operation IDs, and authorization reevaluation exactly once per keyed request. A post-success permission denial now returns the current 403 without replay or idempotency mutation; restoring permission replays the original 202. To preserve handler-origin terminal semantics, keyed pre-claim authorization denials return no exposed operation ID while keyless denials remain correlated; handler-origin forbidden results still snapshot/replay through HTTP. Focused Deferred/HTTP/Inline PHPUnit passes 79 tests / 394 assertions (one existing deprecation); full Mago Analyze passes with no issues; focused format, management-ID, and diff check pass. Scoped Mago lint was retried but Docker image access was intermittently denied after the passing pre-correction lint (warnings only). PostgreSQL two-connection concurrency and evidence-based Recovery remain intentionally unchecked.

## P19-003 Correction Round 4B (PostgreSQL Store Verification)

Added bounded PostgreSQL idempotency store tests in `tests/Internal/Idempotency/PostgreSqlIdempotencyStoreTest.php`. Coverage includes two-connection `pcntl_fork` claim contention (one `Claimed`, one `ExistingSameFingerprint`, one persisted original operation), terminalize operation/fingerprint/expected-state guards, cross-store completed/rejected/internal-failure typed result and safe HTTP snapshot round trips, invalid strategy/version rows failing closed with generic `DeferredTransportException` messages, projection CHECK rejection, and schema-helper/migration parity with the unique operation constraint and no redundant operation index. At that checkpoint recovery was still pending; it is implemented and tested in Correction Round 4D. Focused PostgreSQL PHPUnit passed three consecutive runs at 7 tests / 73 assertions; focused format passed; focused Mago Analyze retains the repository's PHPUnit dependency baseline; Deptrac passed with 0 violations / 3,025 allowed; management-ID and diff checks passed. No production changes or commit were made.

## P19-003 Correction Round 4C (Retention Lifecycle)

Added real PostgreSQL retention lifecycle coverage in `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeServiceTest.php`: expired terminal idempotency records remain `ExistingSameFingerprint` and appear in the retention plan until purge; active legal holds exclude records from plan and purge; a hold placed after dry-run planning blocks delete at purge-time, release permits exactly one delete, records a safe audit with target/count/operation/policy/actor only, and permits a new claim with a new operation ID. `RetentionPlanCommandTest` now proves the explicit idempotency retention option and target count, `RetentionPurgeCommand` exposes `idempotency_records_deleted`, and the service/CLI counts are asserted. Affected retention/planner/console/scheduler suite passed 16 tests / 99 assertions after the CLI count assertion. Full Mago Analyze passed, focused format passed, Deptrac passed with 0 violations / 3,025 allowed, and management-ID/diff checks passed. Recovery is covered by Correction Round 4D; no commit was made.
