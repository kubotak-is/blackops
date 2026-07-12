# P1-015: PostgreSQL Canonical Journal Store

Status: Accepted

## Goal

Canonical Journal StoreをPostgreSQLへ保存・読み出しできるようにし、Inline Vertical SliceのJournal永続化先を準備する。

## In Scope

- PostgreSQL `journal` Table用Migration SQLを追加する
- PostgreSQL用Canonical Journal Store Adapterを追加する
- `JournalRecord` を `bytea` 保存用JSON bytesへEncode／Decodeする内部Codecを追加する
- `record_id` Primary Keyと `(operation_id, sequence)` Unique制約を検証する
- Operation ID単位でSequence順にJournal Recordを読み出す
- DB統合TestとDocumentationを追加する

## Out of Scope

- `operations`、`outcomes`、`dead_letters` Tableの実装
- Deferred OperationのClaim、Lease、Fencing
- Framework CLI Migration Command
- 暗号化、Sensitive Projection、Upcaster Chain
- InlineDispatcherの接続先をPostgreSQLへ差し替えるDI実装

## Relevant Specifications

- `develop/spec/26-journal-ports.md`
- `develop/spec/27-journal-sequence-allocation.md`
- `develop/spec/34-mvp-database-transport.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`

## Files Allowed to Change

- `src/Journal/**`
- `src/Transport/**`
- `tests/Database/**`
- `tests/Transport/**`
- `docs/internals/**`
- `migrations/**`
- `deptrac.yaml`
- `mago.toml`
- `develop/orchestration/tasks/P1-015-postgresql-canonical-journal-store.md`
- `develop/orchestration/reports/P1-015-postgresql-canonical-journal-store.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- PHP `serialize()` をCanonical Journalの保存形式に使用しない
- Production起動時に暗黙DDLを実行しない

## Acceptance Criteria

- [ ] Migration SQLがPostgreSQL Schemaと `journal` Tableを作成する
- [ ] `journal.record_id` がPrimary Keyである
- [ ] `journal.operation_id, sequence` がUniqueである
- [ ] `encoded_record` が `bytea` で保存される
- [ ] Storeが `CanonicalJournalStore` を実装する
- [ ] StoreがappendしたRecordをOperation ID単位・Sequence順で読み戻せる
- [ ] 重複Record IDまたは重複Sequenceは `JournalWriteFailed` になる
- [ ] Formatterを含む必須品質Commandが成功する
- [ ] PHP Comment／DocBlockに管理番号を含めない

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

`develop/orchestration/reports/P1-015-postgresql-canonical-journal-store.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
