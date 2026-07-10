# P5-005: Purge Audit Contract

Status: Completed

## Goal

Retention Purge結果をPayloadなしで監査記録として残すためのContractを実装する。

## In Scope

- Purge Audit Record
- Purge Audit Target種別
- Purge Audit ID
- Purge Audit保存Port
- Unit Testと内部Documentation更新

## Out of Scope

- PostgreSQL Purge Audit Store
- Tombstone実行Service
- Purge Plan / Purge Service
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `spec/39-retention-runtime.md`
- `decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `src/Core/Identifier/**`
- `src/Internal/Identifier/**`
- `tests/Core/Retention/**`
- `tests/Core/Identifier/**`
- `tests/Internal/Identifier/**`
- `docs/internals/**`
- `orchestration/tasks/P5-005-purge-audit-contract.md`
- `orchestration/reports/P5-005-purge-audit-contract.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Purge Audit RecordへPayloadを含めない
- 削除対象自身のJournalへPurge Eventを追加しない
- Purge実行そのものは後続Taskへ分離する

## Resolved Decision

Purge Audit Contract方針は次で確定した。

- `RetentionPurgeAuditId` を専用UUIDv7 Value Objectとして追加する
- 対象Operation IDは `OperationId` 必須とする
- 対象種別は `RetentionPurgeTarget` として、`transport_payload` / `journal` / `outcome` / `dead_letter` を持つ
- Policy表現は非空文字列の `RetentionPolicyRef` とする
- 実行Actorは既存の `RetentionActorRef` を使う
- 保存Portは `record(RetentionPurgeAuditRecord $record): void` の最小構成にする
- System Log連携はPurge Service側でLoggerへ別配送する後続Taskへ分離する

## Acceptance Criteria

- [x] Purge Audit RecordのPublic API方針が確定している
- [x] Payloadを含まないAudit Recordが表現される
- [x] 対象Operation ID、対象種別、件数、Policy、実行時刻、実行Actorを表現できる
- [x] 保存Portが定義される
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

`orchestration/reports/P5-005-purge-audit-contract.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
