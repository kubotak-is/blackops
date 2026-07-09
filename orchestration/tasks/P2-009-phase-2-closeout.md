# P2-009: Phase 2 Closeout

Status: Accepted

## Goal

Phase 2: Projection and Loggingの成果を照合し、最終検証を実行してPhase 3へ進める状態にする。

## In Scope

- Phase 2で追加したProjection/Logging/Scope/Runtime composition成果を照合する
- P2-001からP2-008のReportとSTATEがAcceptedで揃っていることを確認する
- Phase 2最終品質Commandを実行する
- STATEをPhase 3準備状態へ更新する
- Task Reportを作成する

## Out of Scope

- 新しいProduction Code実装
- Phase 3 Deferred Vertical Sliceの実装
- D047 Frontend Integrationの決定

## Relevant Specifications

- `spec/10-logging-and-traceability.md`
- `spec/25-sensitive-projection.md`
- `spec/26-journal-ports.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `orchestration/tasks/P2-009-phase-2-closeout.md`
- `orchestration/reports/P2-009-phase-2-closeout.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Production Codeは変更しない
- Checkpoint timestampは秒とUTC Offsetを含むISO 8601形式で記録する

## Acceptance Criteria

- [x] P2-001からP2-008のTask/Reportが存在し、Acceptedである
- [x] Phase 2成果がProjection and Logging scopeを満たす
- [x] 最終品質Commandが成功する
- [x] STATEがPhase 3準備状態へ更新される
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

`orchestration/reports/P2-009-phase-2-closeout.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
