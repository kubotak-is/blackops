# P4-006: Claim Settlement

Status: Completed

## Summary

`ClaimSettlement` のPostgreSQL実装を追加した。Settlementは低レベルTransport Portとして扱い、Lifecycle Journal Eventは発行しない。

`acknowledge()` はTerminal StateかつClaim Token一致を検証する。`release()` はAttempt開始前のRunning Claimだけを`accepted`へ戻し、Lease情報を解除して`available_at`を更新する。Attempt開始後のRunning OperationやStale ClaimからのSettlementは拒否する。

## Changed Files

- `develop/orchestration/tasks/P4-006-claim-settlement.md`
- `develop/orchestration/reports/P4-006-claim-settlement.md`
- `develop/STATE.md`
- `docs/internal/deferred-transport-contract.md`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLeaseStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationReceiver.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationReceiverTest.php`

## Decisions and Assumptions

- P4-006はClaim Settlementとして切る。
- Interface形状は確定済みと判断した。
- `ClaimSettlement` は低レベルTransport Portとし、Journal Eventは発行しない。
- `acknowledge()` はTerminal State検証のみを行い、Stateを変更しない。
- `release()` はAttempt開始前のRunning Claimだけを`accepted`へ戻す。
- Completion / Failure / Retry / Dead Letterは引き続きLifecycle StoreがJournal込みで確定する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeferredOperationReceiverTest
Result: OK (10 tests, 51 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (373 tests, 1093 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 852 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `acknowledge()` / `release()` のPostgreSQL State Semanticsが確定している
- [x] Claim TokenとFencing Tokenが検証される
- [x] Stale ClaimからのSettlementが拒否される
- [x] `ClaimSettlement` がPostgreSQL Transportで実装される
- [x] 必須Commandがすべて成功している

## Remaining Issues

None.

## Suggested Next Action

P4-007へ進み、Phase 4の次Taskを開始する。
