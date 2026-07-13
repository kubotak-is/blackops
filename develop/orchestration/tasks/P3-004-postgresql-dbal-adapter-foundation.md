# P3-004: PostgreSQL DBAL Adapter Foundation

Status: Accepted

## Goal

既存PostgreSQL AdapterをDoctrine DBAL Connectionへ移行し、Deferred受付Orchestratorで同一Transactionを扱うためのDatabase Access基盤を整える。

## In Scope

- PostgreSQL Canonical Journal StoreをDBAL Connectionへ移行する
- PostgreSQL Deferred Operation SenderをDBAL Connectionへ移行する
- PostgreSQL Integration TestをDBAL Connectionで構成する
- PDO直接利用をPostgreSQL Adapter実装から排除する
- Deferred Transport / PostgreSQL Documentation、Task Report、STATEを更新する

## Out of Scope

- Deferred受付Orchestrator実装
- Operation State保存とCanonical Journal記録の同一Transaction統合
- Doctrine Migrations Command実装
- Claim、Heartbeat、Acknowledge、Release実装
- Worker Runtime
- HTTP 202 Response変換

## Relevant Specifications

- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/041-postgresql-transport-schema.md`
- `develop/decisions/042-postgresql-transaction-boundaries.md`
- `develop/decisions/057-database-access-and-migration-library.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `tests/Http/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P3-004-postgresql-dbal-adapter-foundation.md`
- `develop/orchestration/reports/P3-004-postgresql-dbal-adapter-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- Doctrine ORMを使わない
- PostgreSQL固有SQLは明示SQLのまま扱う
- 既存のJournal Store / Senderの振る舞いを維持する

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

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P3-004-postgresql-dbal-adapter-foundation.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
