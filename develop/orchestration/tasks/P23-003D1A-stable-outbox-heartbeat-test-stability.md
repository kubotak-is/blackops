# P23-003D1A: Stable Outbox Heartbeat Test Stability

Status: Ready

Updated At: 2026-09-04T18:03:54+09:00

## Goal

Remove the known real-time scheduling flake from the Stable generic Outbox
heartbeat test without changing Production behavior or weakening its contract.
The test must deterministically prove initial alarm arming, two heartbeat
callbacks, automatic re-arming, use of the independent heartbeat connection,
successful delivery, and the final persisted `sent` state.

## Context

P23-003D1 Iteration stopped twice at
`OutboxRelayRuntimeTest::testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection`.
The test arms a one-second alarm, blocks for 2.2 seconds, and requires two
callbacks. The callback performs a synchronous PostgreSQL heartbeat before
re-arming, leaving only about 0.2 seconds for database and scheduler delay.
The unchanged test passed an isolated diagnostic immediately after the latest
failure, and earlier reports record the same fail／pass timing behavior.

The failure is outside D1 exact10 and cannot be waived or repaired inside D1.
This bounded prerequisite Task follows the already Accepted deterministic
signal-test pattern: explicitly deliver SIGALRM during the synchronous sender,
inspect the armed timer before each delivery, and keep the real independent
PostgreSQL heartbeat callback.

## Source of Truth

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task
4. `develop/orchestration/tasks/P23-003D1-framework-operational-audit-catalog.md`
5. `develop/orchestration/reports/P23-003D1-framework-operational-audit-catalog.md`
6. `develop/orchestration/tasks/P8-001A-signal-heartbeat-test-stability.md`
7. `src/Internal/Outbox/PcntlOutboxSignalHeartbeat.php`
8. `src/Internal/Outbox/OutboxRelayRuntime.php`
9. `tests/Internal/Outbox/OutboxRelayRuntimeTest.php`

## Files Allowed

Production／Test exact1:

```text
tests/Internal/Outbox/OutboxRelayRuntimeTest.php
```

Worker-managed exact2:

```text
develop/orchestration/reports/P23-003D1A-stable-outbox-heartbeat-test-stability.md
develop/STATE.md
```

This Task is Orchestrator-owned and immutable during worker implementation.
The existing D1 exact10 and D1 Report must remain byte-identical. No other path
may move.

## Required Design

- Do not change any Production source, heartbeat interval, lease, grace,
  callback count, database schema, or final row assertion.
- Remove the fixed 2.2-second success dependency from the named test.
- During the synchronous sender callback, inspect and cancel the currently
  armed alarm, explicitly send SIGALRM to the current process, and dispatch it.
- Repeat that sequence twice. Before each signal, prove the one-second alarm is
  armed; the second observation proves automatic re-arming after the first
  real heartbeat callback.
- Keep the callback's real `PostgreSqlOutboxStore::heartbeat()` on the separate
  connection and require exactly two signal-driven callbacks.
- Preserve the existing POSIX-unavailable skip and restore behavior owned by
  `PcntlOutboxSignalHeartbeat`.
- A bounded timeout fallback is allowed only as failure containment; elapsed
  wall time must not be the success oracle.
- Do not weaken the assertion to one heartbeat and do not invoke the heartbeat
  closure directly.

## Required Fixtures and Checks

1. Focused format check for the exact1 Test file.
2. The named test passes 20 consecutive executions, each with the same test and
   assertion count.
3. The four-file Stable generic Outbox suite passes 5 consecutive executions,
   each with the same test and assertion count.
4. Full PHPUnit passes once before Acceptance.
5. The Outbox Production files and D1 exact10 remain byte-identical to the
   supplied pre-correction freeze.
6. Forbidden management-ID scan, `git diff --check`, staged0, and exact scope
   pass.

Run:

```bash
docker compose run --rm app mago format --check tests/Internal/Outbox/OutboxRelayRuntimeTest.php
for run in $(seq 1 20); do docker compose run --rm app php vendor/bin/phpunit --display-warnings --filter testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection tests/Internal/Outbox/OutboxRelayRuntimeTest.php; done
for run in $(seq 1 5); do docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Outbox/OutboxRelayRuntimeTest.php tests/Internal/Application/ApplicationOutboxRelayConfigurationTest.php tests/Internal/Console/OutboxRelayCommandsTest.php tests/Internal/Scheduler/OutboxRelayMaintenanceTaskTest.php; done
docker compose run --rm app php vendor/bin/phpunit --display-warnings
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' tests/Internal/Outbox/OutboxRelayRuntimeTest.php
git diff --check
git diff --cached --quiet
```

Stop on first failure. Record exact commands, exits, counts, hashes, and any
Not Verified boundary in the Report.

## Acceptance Criteria

- [ ] exact1 Test-only correction implements the deterministic signal design
- [ ] Production and D1 exact10 remain byte-identical
- [ ] focused 20／20 and Stable suite 5／5 are Green with fixed counts
- [ ] full PHPUnit is Green
- [ ] format, management-ID, diff, staged, and scope guards are Green
- [ ] independent Orchestrator review is Green
- [ ] Report and STATE are synchronized
- [ ] bounded correction is committed without D1 Product／Test bytes
- [ ] D1 restarts from a clean new prerequisite HEAD and command 1

## Out of Scope

- Production behavior or source changes
- changing the heartbeat, lease, or grace contract
- weakening heartbeat count or replacing the real database callback
- D1 Product／Test or D1 Report changes
- package export, push, release, deploy, publication, browser, or CI
