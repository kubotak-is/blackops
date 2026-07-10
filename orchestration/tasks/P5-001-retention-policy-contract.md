# P5-001: Retention Policy Contract

Status: Completed

## Goal

Phase 5: Retentionの最初の土台として、Retention対象と保持期間を表すPublic Contractを実装する。

## In Scope

- Retention対象のPublic enum
- 明示設定された保持期間のPublic Value Object
- 4対象すべてを持つRetention Policy
- Unit Testと内部Documentation更新

## Out of Scope

- Retention Hold Port
- PostgreSQL Retention Schema
- Tombstone実行
- Purge Plan / Purge Service
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `spec/38-data-retention-and-deletion.md`
- `spec/39-retention-runtime.md`
- `decisions/044-data-retention-and-deletion.md`
- `decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `tests/Core/Retention/**`
- `docs/internals/**`
- `TODO.md`
- `orchestration/tasks/P5-001-retention-policy-contract.md`
- `orchestration/reports/P5-001-retention-policy-contract.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Retention期間に暗黙の既定値を設けない
- Production Codeで削除処理を実装しない
- Hold、Audit、Schedulerの実装は後続Taskへ分離する

## Acceptance Criteria

- [x] Retention対象がTransport Payload / Journal / Outcome / Dead Letterで表現される
- [x] Retention期間は明示的な正の期間だけを受け入れる
- [x] Retention Policyは4対象すべての期間を持つ
- [x] Public APIが`#[PublicApi]`で示される
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

`orchestration/reports/P5-001-retention-policy-contract.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
