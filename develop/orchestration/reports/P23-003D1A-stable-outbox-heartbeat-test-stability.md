# P23-003D1A: Stable Outbox Heartbeat Test Stability Report

Status: Accepted — bounded candidate commit pending

Updated At: 2026-09-05T00:23:29+09:00

## Summary

The D1 Iteration Gate exposed the existing Stable generic Outbox heartbeat
timing flake again. The configured replacement worker changed one Test file
only and replaced its 2.2-second natural-alarm success condition with
deterministic explicit SIGALRM delivery and re-arm evidence. The Orchestrator
then completed the required verification after the worker stopped responding.
Production behavior and all D1 exact10 bytes remain immutable.

## Changed Files

- `tests/Internal/Outbox/OutboxRelayRuntimeTest.php`
- `develop/orchestration/tasks/P23-003D1A-stable-outbox-heartbeat-test-stability.md`
- `develop/orchestration/reports/P23-003D1A-stable-outbox-heartbeat-test-stability.md`
- `develop/STATE.md`

The Task is Orchestrator-owned authority. The pre-existing D1 exact12 worktree
is retained and is not part of the Test correction.

## Decisions and Assumptions

- The failed D1 Gate and the subsequent isolated pass are both retained as
  evidence; the pass does not waive or continue the failed Gate.
- The correction preserves two heartbeat callbacks and a real independent
  PostgreSQL heartbeat connection.
- The test-only sender records and cancels the current alarm, sends SIGALRM to
  the same process, and explicitly dispatches it twice. Both recorded alarm
  values are positive and at most the configured one-second interval; the
  second value proves the first real database callback re-armed supervision.
- Elapsed wall time is no longer a success condition. The focused test now
  completes in about 0.1 seconds while preserving the real database writes.
- D1 restarts fully after this correction is accepted and committed.

## Commands and Results

```text
docker compose run --rm app mago format --check tests/Internal/Outbox/OutboxRelayRuntimeTest.php
  -> exit 0; formatted

docker compose run --rm app php vendor/bin/phpunit --display-warnings --filter testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection tests/Internal/Outbox/OutboxRelayRuntimeTest.php
  -> exit 0; setup confirmation 1 test / 10 assertions

20 sequential direct invocations of:
docker compose run --rm app php vendor/bin/phpunit --display-warnings --filter testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection tests/Internal/Outbox/OutboxRelayRuntimeTest.php
  -> 20/20 exit 0; each 1 test / 10 assertions

5 sequential direct invocations of:
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Outbox/OutboxRelayRuntimeTest.php tests/Internal/Application/ApplicationOutboxRelayConfigurationTest.php tests/Internal/Console/OutboxRelayCommandsTest.php tests/Internal/Scheduler/OutboxRelayMaintenanceTaskTest.php
  -> 5/5 exit 0; each 10 tests / 75 assertions

docker compose run --rm app php vendor/bin/phpunit --display-warnings
  -> exit 0; 3,179 tests / 1,815,415 assertions; 1 deprecation / 2 PHPUnit deprecations / 13 PHPUnit notices

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' tests/Internal/Outbox/OutboxRelayRuntimeTest.php
git diff --check
git diff --cached --quiet
  -> all exit 0; no forbidden IDs; staged0

expected_status_paths=(
  develop/STATE.md
  develop/orchestration/reports/P23-003D1-framework-operational-audit-catalog.md
  develop/orchestration/reports/P23-003D1A-stable-outbox-heartbeat-test-stability.md
  develop/orchestration/tasks/P23-003D1A-stable-outbox-heartbeat-test-stability.md
  src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php
  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
  tests/Architecture/AuditPublicApiTest.php
  tests/Architecture/AuditRecordArchitectureTest.php
  tests/Consumer/framework-package-export.sh
  tests/Consumer/framework-package-required-inventory.sh
  tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
  tests/Fixtures/Audit/framework-operational-audit-event-catalog-v1.json
  tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php
  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
  tests/Internal/Outbox/OutboxRelayRuntimeTest.php
)
mapfile -t actual_status_paths < <(git status --short -uall | cut -c4- | sort)
mapfile -t expected_status_paths < <(printf '%s\n' "${expected_status_paths[@]}" | sort)
test "${#actual_status_paths[@]}" -eq 15
test "${actual_status_paths[*]}" = "${expected_status_paths[*]}"

d1_exact10=(
  src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php
  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
  tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php
  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
  tests/Architecture/AuditRecordArchitectureTest.php
  tests/Architecture/AuditPublicApiTest.php
  tests/Fixtures/Audit/framework-operational-audit-event-catalog-v1.json
  tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
  tests/Consumer/framework-package-required-inventory.sh
  tests/Consumer/framework-package-export.sh
)
test "$(sha256sum "${d1_exact10[@]}" | sha256sum | cut -d' ' -f1)" = "cd120083d712ee6d7a5c57abbb36cf568c130ae26a9299f3316f621c6739bf43"
test "$(sha256sum develop/orchestration/reports/P23-003D1-framework-operational-audit-catalog.md | cut -d' ' -f1)" = "8e3772e58a586897544d165c03373d51d8ba958a7454c6d4a599ce3deeeb99f7"
git diff --quiet HEAD -- src/Internal/Outbox/PcntlOutboxSignalHeartbeat.php src/Internal/Outbox/OutboxRelayRuntime.php
  -> all exit 0; status exact15; D1 exact10 and D1 Report hashes exact; Outbox Production diff0
```

The first shell-loop attempt could not access the Docker socket before test
execution. Its escalated replacement later hung before producing a test result
and was terminated; its transient created container was removed automatically.
The direct Docker invocations above are the completed evidence. Scope is exact
15 total worktree paths: pre-existing D1 exact12 plus this Task, this Report,
and the exact1 Test. D1 exact10 remains
`cd120083d712ee6d7a5c57abbb36cf568c130ae26a9299f3316f621c6739bf43`.
The changed Test SHA-256 is
`64797c8b2bff2b9e7506c3b49ef6559ec83f7a0eaa41d15ff5473b2043f85f67`.

Independent technical closure and Documentation／management closure both
returned Green `P1=0/P2=0/P3=0` on the unchanged Test bytes. The Orchestrator
accepted the bounded correction at `2026-09-05T00:23:29+09:00`.

## Acceptance Criteria

- [x] deterministic exact1 Test correction
- [x] focused repetition and Stable suite repetition Green
- [x] full PHPUnit Green
- [x] Production／D1 preservation and repository guards Green
- [x] independent closure review and Acceptance
- [ ] bounded candidate commit

## Remaining Issues

The bounded candidate commit remains open. D1 Iteration／reviews／Final／
Acceptance／commit remain blocked until that commit supplies a clean new
prerequisite HEAD.

## Suggested Next Action

Commit only the bounded D1A Task／Report／Test／STATE paths without staging any D1
Product／Test or D1 Report path. Then update the D1 checkpoint and restart its
Iteration Gate at command 1 on the new clean prerequisite HEAD.
