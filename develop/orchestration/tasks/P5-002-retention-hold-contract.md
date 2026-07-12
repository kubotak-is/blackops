# P5-002: Retention Hold Contract

Status: Completed

## Goal

Retention HoldのPublic ContractとPortを実装し、Operation単位の削除停止をFramework内で表現できるようにする。

## In Scope

- Retention Hold Category
- Retention Hold Record
- Hold設定 / 解除のPort
- Unit Testと内部Documentation更新

## Out of Scope

- PostgreSQL Retention Schema
- Retention Hold Store実装
- Purge Plan / Purge Service
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `src/Core/Identifier/**`
- `src/Internal/Identifier/**`
- `tests/Core/Retention/**`
- `tests/Core/Identifier/**`
- `tests/Internal/Identifier/**`
- `docs/internals/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/P5-002-retention-hold-contract.md`
- `develop/orchestration/reports/P5-002-retention-hold-contract.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Failed / Dead Letteredを理由とする自動Holdは行わない
- Holdは明示設定だけを許可する
- Hold中の削除停止評価と実際のPurgeは後続Taskへ分離する

## Resolved Decision

Retention HoldのPublic Contract方針は次で確定した。

- `RetentionHoldId` を専用UUIDv7 Value Objectとして追加し、既存Identifierと同じ検証・正規化ルールにする
- `RetentionActorRef` を非空文字列のPublic Value Objectとして追加する
- 将来のActor Modelとは直接結合せず、Actor IDや外部System名を安全に記録できるReferenceとして扱う
- Hold解除は同一Hold Recordの`released_at` / `released_by`更新として表す
- Portは `place()` / `release()` / `activeFor(OperationId $operationId)` の最小構成にする

## Acceptance Criteria

- [x] Hold IDとActor ReferenceのPublic API方針が確定している
- [x] CategoryがLegal / Security / Audit / Support / Otherで表現される
- [x] Hold設定と解除を表すContractがある
- [x] Failed / Dead Letteredによる自動HoldをContractが要求しない
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

`develop/orchestration/reports/P5-002-retention-hold-contract.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
