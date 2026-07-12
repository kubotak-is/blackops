# P1-016: Inline Dispatcher PostgreSQL Journal Integration - Implementation Report

Status: Accepted

## Summary

InlineDispatcherへPostgreSQL Canonical Journal Storeを注入した統合Testを追加し、Completed／RejectedのLifecycle Journal列がPostgreSQLへ永続化されることを検証した。

Production Codeの変更は不要で、既存の `CanonicalJournalWriter` Port差し替えだけでDB保存まで通ることを確認した。

## Changed Files

- `tests/Transport/PostgreSql/PostgreSqlInlineDispatcherIntegrationTest.php` (add): InlineDispatcher + PostgreSQL StoreのCompleted／Rejected統合Testを追加。
- `docs/internals/postgresql-journal-store.md` (edit): InlineDispatcher注入時のLifecycle永続化を追記。
- `develop/orchestration/tasks/P1-016-inline-dispatcher-postgresql-journal-integration.md` (add): Task Packet。
- `develop/STATE.md` (edit): P1-016進行・完了状態へ更新。

## Decisions and Assumptions

- Runtime DI本体はOut of Scopeとし、Test内でInlineDispatcherへPostgreSQL Storeを直接注入した。
- DB統合Testは専用Schema `blackops_p1_016` を作成・破棄し、既定Schema `blackops` には触れない。
- DB上の唯一Operation IDを取得してから `CanonicalJournalStore::records()` で読み戻し、Store経由の復元結果を検証した。
- テスト用UUID生成器は呼び出しごとに異なるUUIDv7を返す。PostgreSQLの `record_id` Primary Key制約を実際に通すため。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (185 tests, 451 assertions)。Runtime PHP 8.5.7。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 221 / Warnings 0 / Errors 0。

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Inline Completedの4 EventがPostgreSQL Journalへ保存される
- [x] Inline Rejectedの3 EventがPostgreSQL Journalへ保存される
- [x] DBから読み戻したRecordがSequence順である
- [x] DBから読み戻したRecordのOperation TypeがMetadata由来である
- [x] Completed DataとRejected DataがDB往復後も復元される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Runtime DI ContainerでPostgreSQL Storeを実際に構成する実装は未実装。
- HTTP `GET /welcome` のOperation Binding／Responderは未実装。
- CLI Migration Commandは未実装。

## Suggested Next Action

HTTP `GET /welcome` Vertical Sliceへ進み、Route／Binding／Responderの最小実装を追加する。ただしD047 Frontend Integrationが未決のため、HTML Renderingを避けたAPI-only Response Contractとして進める。

## Codex Review

Accepted at `2026-07-08T01:49:48+09:00`。
