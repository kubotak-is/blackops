# P4-004: PostgreSQL Worker Heartbeat

Status: Completed

## Summary

P4-004を完了した。

PostgreSQL Deferred Operation Receiverが`ClaimHeartbeat`を実装し、Running OperationのLeaseをHeartbeatで延長できるようにした。HeartbeatはClaim Token内のOperation IDとFencing Tokenを検証し、古いFencing TokenまたはRunning以外のOperationを拒否する。

## Changed Files

- `docs/internals/deferred-transport-contract.md`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationReceiver.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLeaseStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationMessageCodec.php`
- `src/Transport/PostgreSql/PostgreSqlSystemClock.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationReceiverTest.php`
- `develop/orchestration/tasks/P4-004-postgresql-worker-heartbeat.md`
- `develop/orchestration/reports/P4-004-postgresql-worker-heartbeat.md`
- `develop/STATE.md`

## Decisions and Assumptions

- P4-004はHeartbeatに限定し、Claim Settlement、Lease Expired Recovery、Graceful Shutdown、Signal Handlingは後続Taskへ送る。
- Heartbeatは既存のReceiver設定のLease秒数を使い、Clockを注入可能にしてテストを決定的にした。
- HeartbeatはFencing Tokenを更新せず、同じClaim Tokenを維持する。
- Running以外のOperation、またはStale Fencing TokenのHeartbeatは`DeferredTransportException`で拒否する。
- Receiver本体の責務肥大化を避けるため、Lease SQLとRow-to-Message変換を内部クラスへ分離した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeferredOperationReceiverTest
Result: OK (6 tests, 34 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (368 tests, 1053 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 786 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] PostgreSQL Receiverが`ClaimHeartbeat`を実装している
- [x] HeartbeatがRunning OperationのLeaseを延長する
- [x] HeartbeatがStale Fencing Tokenを拒否する
- [x] HeartbeatがRunning以外のOperationを拒否する
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Claim Settlementは未実装。
- Lease Expired Recoveryは未実装。
- Graceful Shutdown、Signal Handling、Stale Worker Metricは未実装。

## Suggested Next Action

P4-005 Task Packetを作成し、Lease Expired RecoveryまたはClaim Settlementの実装へ進む。
