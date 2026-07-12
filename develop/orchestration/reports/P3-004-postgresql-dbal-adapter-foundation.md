# P3-004: PostgreSQL DBAL Adapter Foundation Report

Status: Accepted

## Summary

既存PostgreSQL AdapterをDoctrine DBAL Connectionへ移行し、Deferred受付Orchestratorで同一Connection / Transactionを扱うためのDatabase Access基盤を整えた。

`PostgreSqlCanonicalJournalStore` と `PostgreSqlDeferredOperationSender` はPDOではなくDBAL `Connection` を受け取る。PostgreSQL固有SQL、`bytea` 保存、`timestamptz` 保存、既存Schemaと既存振る舞いは維持した。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlCanonicalJournalStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSender.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `tests/Transport/PostgreSql/PostgreSqlInlineDispatcherIntegrationTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `docs/internals/postgresql-journal-store.md`
- `docs/internals/deferred-transport-contract.md`
- `develop/orchestration/tasks/P3-004-postgresql-dbal-adapter-foundation.md`
- `develop/orchestration/reports/P3-004-postgresql-dbal-adapter-foundation.md`
- `develop/STATE.md`

## Decisions and Assumptions

- PostgreSQL Adapterの外部構成境界はDoctrine DBAL `Connection` とした。
- DBAL移行後もPostgreSQL固有SQLは明示SQLのまま維持した。
- 既存の `migrate()` はTestや明示的Command用の入口として維持した。Production向けのVersioned Migration Commandは後続Taskへ送る。
- Deferred受付Orchestrator、Operation State保存とCanonical Journal記録の同一Transaction統合は後続Taskへ分離した。
- TestのDB接続はDoctrine DBAL `DriverManager` で作成するように変更した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'PostgreSqlCanonicalJournalStoreTest|PostgreSqlDeferredOperationSenderTest|PostgreSqlInlineDispatcherIntegrationTest|OperationRequestHandlerTest'
Result: OK (19 tests, 92 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (327 tests, 789 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 485 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `PostgreSqlCanonicalJournalStore` がDBAL Connectionで動作する
- [x] `PostgreSqlDeferredOperationSender` がDBAL Connectionで動作する
- [x] PostgreSQL Integration TestがDBAL Connectionを使う
- [x] PostgreSQL Adapter実装からPDO直接依存が消える
- [x] 既存Journal Storeの読み書きTestが成功する
- [x] Deferred Senderの保存Testが成功する
- [x] Documentationが更新される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Deferred受付Orchestratorは未実装。
- Operation State保存とCanonical Journal記録の同一Transaction統合は未実装。
- Doctrine Migrations Commandは未実装。
- Claim、Heartbeat、Acknowledge、Release、Worker Runtime、HTTP 202 Response変換は未実装。

## Suggested Next Action

Deferred受付Orchestratorを実装し、同じDBAL Connection / TransactionでOperation State保存、`operation.received` Journal、`operation.accepted` Journal、初期SequenceをCommitする。
