# P3-009: PostgreSQL Worker Claim Report

Status: Accepted

## Summary

PostgreSQL TransportへWorker Claimを追加した。EligibleなOperationを`FOR UPDATE SKIP LOCKED`で1件取得し、同一Transaction内でStateを`running`へ更新し、Lease Owner、Lease期限、Fencing Token、State Versionを更新する。

Claim成功時はCodec済みMessageと不透明なClaim Tokenを持つ`OperationClaim`を返す。EligibleなOperationがない場合は`null`を返す。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationReceiver.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationReceiverTest.php`
- `docs/internals/deferred-transport-contract.md`
- `orchestration/tasks/P3-009-postgresql-worker-claim.md`
- `orchestration/reports/P3-009-postgresql-worker-claim.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Lease OwnerとLease DurationはPostgreSQL Receiverの構成値として扱い、ExecutionContextや業務Handlerへ露出しない。
- Fencing TokenはOperations行に単調増加整数として保存し、Claim TokenはOperation IDとFencing Tokenから構成する不透明文字列とした。
- Schemaには`attempt_number`、`lease_owner`、`lease_expires_at`、`fencing_token`、`created_at`、`updated_at`を追加した。
- Existing Schemaへ追随できるよう、追加Columnは`ALTER TABLE ... ADD COLUMN IF NOT EXISTS`でも定義した。
- Heartbeat、Settlement、Attempt開始Journal、Lease Expired Recoveryは後続Taskへ残した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeferredOperationReceiverTest
Result: OK (3 tests, 22 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (345 tests, 880 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 620 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] PostgreSQL Operation State SchemaにClaim Metadata列が追加される
- [x] PostgreSQL Operation Receiverが追加される
- [x] Eligible Operationを1件Claimできる
- [x] Claim成功時にStateが`running`へ更新される
- [x] Claim成功時にLease Owner、Lease期限、Fencing Token、State Versionが更新される
- [x] Claim成功時に`OperationClaim`が返る
- [x] Eligible Operationがない場合は`null`が返る
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Heartbeatは未実装。
- Claim Settlement acknowledge / releaseは未実装。
- Attempt開始Journalは未実装。
- Worker RuntimeとHandler実行は未実装。
- Lease Expired RecoveryとRetry Schedulingは未実装。

## Suggested Next Action

WorkerがClaim済みOperationをOperationValue / ExecutionContextへDecodeし、Attemptを開始してHandlerを実行するRuntimeを追加する。
