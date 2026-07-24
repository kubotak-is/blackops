# P19-005 Relay Runtime and BlackOps CLI Report

Status: Accepted

## Summary

Added additive PostgreSQL Outbox Relay persistence and runtime. Records now
support due claims, leases, monotonic fencing, heartbeats, retry scheduling,
safe failure fingerprints, dead-letter settlement, and audited dead-letter
retry. Relay delivery reuses the persisted child message and the existing
PostgreSQL Deferred sender; exact duplicate messages are acknowledged as
durable replays without changing the original acceptance timestamp.

Added `outbox:relay:run`, `outbox:relay:daemon`, and
`outbox:dead-letter:retry` to the Framework command set, relay configuration
validation, scheduler work-unit registration, example configuration, and
internal/guide documentation. Tracked terminology is now `BlackOps CLI`; the
`project-cli.md` filename and public slug remain unchanged.

Correction added a PCNTL alarm supervisor for each blocking delivery. It uses
the already-separated heartbeat PostgreSQL connection, periodically refreshes
the active claim during `enqueue()`, enforces the configured grace deadline,
and restores async-signal mode, handlers, and any prior alarm on exit. Daemon
iterations now run inside one reusable signal scope, so SIGTERM/SIGINT stop
new claims while the current delivery receives the configured grace period.

The correction also closes the upgrade and integrity boundaries: the additive
migration replaces the historical `state_version = 1` constraint and its down
path normalizes relay rows back to the P19-004 pending contract; claim envelope
metadata and failure fingerprints are validated before persistence. Real
PostgreSQL tests now cover overlapping `SKIP LOCKED`, stale ownership, upgrade
up/down, crash-window replay, retry/dead-letter isolation, and command/scheduler
surfaces.

Architecture correction moved the relay claim DTO into the PostgreSQL transport
namespace (`PostgreSqlOutboxClaim`). Internal relay and signal code consumes that
transport-owned claim, so the transport adapter no longer depends on an Internal
class and the full Deptrac direction remains enforced.

## Changed Files

- PostgreSQL Outbox schema/store/record/sender and additive migration
- PostgreSQL Outbox claim plus Internal Outbox configuration/result/runtime classes
- Application Outbox configuration/runtime and console composition
- Relay run/daemon/dead-letter commands and Framework command reservation
- Scheduler relay task and example execution configuration
- Relay guide/internal documentation and scoped migration/sender/console tests
- Repository tracked terminology text synchronization

## Decisions and Assumptions

- P19-004 remains an immutable historical migration; P19-005 down removes only
  relay columns, indexes, constraints, and the retry audit table.
- Failure fingerprints are `v1:` plus SHA-256 of the domain separator and
  Throwable class only.
- Dead-letter retry preserves record identity, child operation identity, and
  attempt count; audit stores actor, reason, timestamp, and previous count.
- PCNTL is required for the daemon and invalid configuration/options fail before
  claim. Raw payload, context, SQL, credentials, and Throwable messages are not
  printed or persisted by the relay surface.

## State / Claim / Fencing Matrix

| Boundary | Implementation |
| --- | --- |
| Eligible claim | `pending`/`retry_scheduled` due rows and expired `leased` rows, ordered by due time and record id with `FOR UPDATE SKIP LOCKED` |
| Ownership | relay id, lease expiry, incremented fencing token, attempt count, and state version |
| Heartbeat/settlement | record id + relay id + fencing token + `leased` state predicate; stale updates affect zero rows and raise a safe exception |
| Lease recovery | expired lease is reclaimable without changing record or child operation identity |
| Blocking delivery heartbeat | `PcntlOutboxSignalHeartbeat` arms `SIGALRM` at `heartbeat_seconds`; callback writes through the independent heartbeat store connection |
| Signal lifecycle | Previous async mode, `SIGALRM`/`SIGTERM`/`SIGINT` handlers, and alarm schedule are restored; loop state resets for reuse |

## Delivery / Crash / Retry Matrix

| Case | Result |
| --- | --- |
| Transport acceptance | Existing Deferred sender receives the stored message and Outbox row settles `sent` |
| Acceptance-before-sent crash | Reclaim sends the same child operation/message; sender returns replay acknowledgement and preserves original `accepted_at` |
| Retryable failure | Bounded exponential `next_attempt_at` and versioned safe fingerprint |
| Maximum attempt | `dead_lettered` with lease metadata cleared |
| Batch isolation | Each claim is settled independently; one sender failure does not stop later records |

## Dead Letter / Audit Matrix

Dead-letter retry requires the row to already be `dead_lettered`; otherwise it
fails safely without an audit row. A valid retry inserts only the audit id,
record id, child operation id, actor, reason, execution time, and previous
attempt count, then returns the same row to `retry_scheduled`.

## BlackOps CLI / Scheduler Matrix

- `outbox:relay:run`: one batch by default, `--batches`, or `--until-empty`; it
  prints only claimed/sent/retried/dead-lettered/stale counters.
- `outbox:relay:daemon`: millisecond polling, optional iteration cap, and
  SIGTERM/SIGINT stop-new-claims behavior; fails fast without PCNTL.
- `outbox:dead-letter:retry`: explicit record id, actor, and reason.
- Scheduler registers relay as an independent finite maintenance task when
  `execution.outbox_relay` is configured.
- Daemon wraps its whole iteration/sleep loop in the relay signal scope and
  checks the persistent stop flag before claiming another batch.

## Terminology Compatibility Evidence

`! rg -n 'Project[ ]CLI'` passes across tracked source. Canonical command names
remain prefixless, the official entrypoint remains `php blackops <command>`,
and `project-cli.md` plus its public slug are unchanged.

## Sensitive Evidence

No payload/context/credential/connection parameter/SQL/Throwable detail is
stored in relay audit or failure fingerprint fields, or emitted by commands.

## Commands and Results

- Focused PostgreSQL, sender, relay runtime, signal, scheduler, application,
  and CLI tests — PASS (58 tests, 312 assertions in the final database/runtime
  run; the blocking runtime test holds `enqueue()` for 2.2 seconds and observes
  at least two heartbeats through a second PostgreSQL connection).
- `mago format --check src tests examples` — PASS.
- `mago analyze` — PASS (no issues found).
- `mago lint` — PASS (no errors or warnings; one existing SapiRuntime note,
  one existing RuntimeContainerCompiler help, and two non-blocking relay
  readability helps).
- `vendor/bin/deptrac analyse --no-progress` — PASS (0 violations, 57 uncovered,
  3,175 allowed; the PostgreSQL claim DTO now stays inside the Transport layer).
- Full PHPUnit — PASS (1,850 tests, 7,495 assertions, one existing deprecation).
- Management-ID guard and `git diff --check` — PASS.
- Documentation website test/build — PASS (42 reader tests; 32 static pages;
  artifact boundary, navigation/accessibility, and Pagefind checks passed).
- Framework Package Export — PASS against reviewed implementation commit
  `7e72173`.
- Fresh Community Board Clean Install — PASS, including 9 migrations,
  compile/generation freshness, Svelte check, 43 frontend tests, frontend
  production build, seed determinism, and HTTP journey.
- GitHub Actions CI run `30065678319` — PASS on closeout commit `2c88418`
  (Documentation Website, Mago/PHPUnit/Deptrac, Frontend Contract/Runtime,
  Community Board Clean Install/Seed, and Full-stack Product Journey).
- Documentation Delivery run `30065678317` — PASS. The verified artifact was
  built; production deployment was skipped by the existing credential gate.

## Acceptance Criteria

- [x] Additive relay schema, claim, lease, heartbeat, fencing, retry, and dead-letter settlement implemented.
- [x] Fixed child identity and duplicate transport replay semantics implemented.
- [x] Run/daemon/dead-letter CLI, configuration validation, scheduler task, and terminology synchronization implemented.
- [x] Sensitive output/storage boundaries preserved.
- [x] Full PHPUnit, Mago format/lint/analyze, Deptrac, and documentation website
  gates pass under Orchestrator verification.
- [x] Blocking-delivery heartbeat, lease-loss, signal restoration, grace expiry,
  and helper reuse are directly tested; one real PostgreSQL runtime test proves
  heartbeat writes on the separate connection while transport blocks.
- [x] Overlapping claim lock, transport-acceptance crash-window, retry/backoff/
  dead-letter, stale settlement, command option/output, daemon reuse, scheduler
  isolation, configuration validation, and exact duplicate-integrity matrices
  are directly tested.
- [x] Framework Package Export and Fresh Community Board Clean Install pass
  against reviewed implementation commit `7e72173`.

## Remaining Issues

No remaining issue is known within the Task scope.
No production scope outside the packet was changed.

## Suggested Next Action

Prepare P19-006 Canonical Observer Replay.
