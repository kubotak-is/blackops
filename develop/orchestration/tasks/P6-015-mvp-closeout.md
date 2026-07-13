# P6-015: MVP Closeout

Status: Completed

## Goal

MVP Definition of Doneを実装・Test・Runtime証拠と対応付け、TODOとDocumentationの古い状態を整理して、Phase 6とMVP全体を再開可能な形でCloseoutする。

## In Scope

- D017 Definition of Done全項目の証拠Table
- Inline／Deferred／Retry／Dead Letter／Recovery／Sensitive／Compile／RetentionのMVP到達点整理
- Architecture OverviewとInline／Deferred／障害時Sequence Diagram
- Application開発者向けMVP Status／実行・検証導線
- `develop/TODO.md` の実装済み／名称変更済み／MVP後の分類整理
- `develop/DOCS.md` の現行仕様／Guide／Internals導線と古い仮称の注記
- Guide／Internals Indexの更新
- MVP最終品質CommandとSample E2Eの再実行
- Closeout Reportと`develop/STATE.md`のMVP Complete Checkpoint

## Out of Scope

- Production Code／Public API／Testの新規機能変更
- Transactional Outbox／Replay CLI
- Authentication／Authorization
- Deferred Status／Outcome HTTP Endpoint／Client SDK
- Encryption／Remote Observability Adapter
- SQLite／MySQL／SQS／Kafka
- Generator／Admin UI／Scheduled Operation Strategy
- Composer Packagist公開／Git Tag／GitHub Release／Push

## Relevant Specifications and Decisions

- `develop/decisions/017-mvp-scope.md`
- `develop/decisions/046-mvp-delivery-plan.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/14-package-architecture.md`
- `develop/spec/28-mvp-lifecycle-events.md`
- `develop/spec/34-mvp-database-transport.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/DOCS.md`
- `docs/guide/README.md`
- `docs/guide/mvp-status.md`
- `docs/internal/README.md`
- `docs/internal/architecture.md`
- `develop/orchestration/tasks/P6-015-mvp-closeout.md`
- `develop/orchestration/reports/P6-015-mvp-closeout.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- Production CodeやTestをCloseout便宜で変更しない
- TODOは機械的に全項目完了にせず、実装またはDecision証拠を確認できる項目だけ更新する
- 古い名称の項目は、現行名称で実現したことが明確な場合だけ文言を同期する
- MVP後の項目、明示的Out of Scope、未実装項目は未完了のまま残す
- Definition of DoneはTask Report／Test Class／Commandの実在証拠に紐付ける
- Canonical JournalがSensitive値を再現性のため保持し、Observed／System Logで安全化する境界を「Journalに一切保存しない」と誤記しない
- Mermaid Diagramは現在のCode PathとTransaction境界に合わせる
- MVP CompleteとProduction Ready／Stable Releaseを混同しない

## Acceptance Criteria

- [x] D017 Definition of Doneの10項目がすべて証拠付きでSatisfiedと確認される
- [x] Inline／Deferred／Retry／Recovery／Dead Letter／Sensitive／Compile／Retentionの到達点が文書化される
- [x] Architecture OverviewとInline／Deferred／障害時Sequence Diagramが現行実装と一致する
- [x] TODOの既実装・既決定の旧い未完了表示が証拠範囲で修正される
- [x] 未実装／MVP後の項目が未完了のまま保存される
- [x] DOCS／Guide／InternalsからMVP到達点とArchitectureへ到達できる
- [x] MVPの既知制約とPost-MVP境界が明記される
- [x] Sample E2Eと全品質Commandが成功する
- [x] `develop/STATE.md`がMVP Completeと次の任意Actionを示す

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-015-mvp-closeout.md` に次を記録する。

- Summary
- MVP Definition of Done Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Post-MVP Work
- Suggested Next Action
