# P3-002: PostgreSQL Deferred Sender Report

Status: Accepted

## Summary

PostgreSQLへDeferred Operation MessageをDurable保存し、保存成功時に`DeferredAcknowledgement`を返すOperationSender実装を追加した。

`operations` tableはPayloadとContextを不透明な`bytea`として保持し、State、Version、Sequence、Available At、Accepted Atを永続化する。初期Stateは`accepted`、初期State Versionは`1`、初期Next Sequenceは`1`とした。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSender.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `docs/internals/deferred-transport-contract.md`
- `develop/orchestration/tasks/P3-002-postgresql-deferred-sender.md`
- `develop/orchestration/reports/P3-002-postgresql-deferred-sender.md`
- `develop/STATE.md`

## Decisions and Assumptions

- このTaskは低レベルTransport SenderのDurable保存に限定した。
- `operation.received` / `operation.accepted` Canonical Journalを同一Transactionで書くDeferred受付Orchestratorは後続Taskへ分離した。`DeferredOperationMessage` はCodec済みMessageであり、この層だけではCanonical Journal Dataを正しく生成しないため。
- `content_type` は `application/vnd.blackops.deferred-operation+json`、`encoding` は `utf8`、`key_id` はMVPでは `null` とした。
- `available_at` はMessage由来、`accepted_at` はSenderの保存時刻とした。
- Transport layerはdeptrac上Library layerへ依存できないため、PSR Clock依存は持たせなかった。実装時刻は`DateTimeImmutable('now')`で取得し、Integration Testでは固定時刻を注入できるようにした。
- Claim、Heartbeat、Acknowledge、Release、Worker Runtime、HTTP 202 Response変換は後続Taskへ送った。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeferredOperationSenderTest
Result: OK (3 tests, 29 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (327 tests, 788 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 483 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] PostgreSQL Deferred Operation Schemaが作成される
- [x] SchemaにPayload、Context、Content Type、Encoding、Key ID、State、Version、Sequence、Available At、Accepted Atが含まれる
- [x] `OperationSender::enqueue()` がMessageを保存し、`DeferredAcknowledgement`を返す
- [x] 保存されたPayloadとContextが `bytea` である
- [x] 初期Stateが `accepted`、初期Versionが1、初期Sequenceが1として保存される
- [x] Duplicate Operation ID等の保存失敗が `DeferredTransportException` へ変換される
- [x] PostgreSQL Integration Testが追加される
- [x] Internals Documentationが更新される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Deferred受付OrchestratorでOperation State保存とCanonical Journal記録を同一Transactionへ統合する必要がある。
- Claim、Heartbeat、Acknowledge、Releaseは未実装。
- Worker Runtimeは未実装。
- HTTP 202 Response変換は未実装。
- Canonical CodecとOpenAPI生成は未実装。

## Suggested Next Action

Deferred受付Orchestratorを実装し、Operation State保存、`operation.received` Journal、`operation.accepted` Journal、初期Sequenceを同一TransactionでCommitできるようにする。
