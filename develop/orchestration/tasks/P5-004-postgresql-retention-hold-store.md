# P5-004: PostgreSQL Retention Hold Store

Status: Completed

## Goal

`RetentionHoldPort` のPostgreSQL実装を追加し、Retention Holdの設定、解除、Active Hold取得を永続化できるようにする。

## In Scope

- PostgreSQL Retention Hold Store
- Hold ID生成Port
- `place()` / `release()` / `activeFor()` の実装
- Unit Testと内部Documentation更新

## Out of Scope

- Tombstone実行Service
- Purge Plan / Purge Service
- Purge Audit Table
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P5-004-postgresql-retention-hold-store.md`
- `develop/orchestration/reports/P5-004-postgresql-retention-hold-store.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Transport LayerからInternal IdentifierFactoryへ依存しない
- Failed / Dead Letteredによる自動Holdは行わない
- StoreはPayloadやJournalのPurgeを行わない

## Acceptance Criteria

- [x] PostgreSQL Storeが`RetentionHoldPort`を実装する
- [x] `place()` がHoldを保存し、保存済みRecordを返す
- [x] `release()` が同一Hold Recordを解除済みに更新する
- [x] `activeFor()` が未解除Holdだけを返す
- [x] 必須Commandがすべて成功している

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P5-004-postgresql-retention-hold-store.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
