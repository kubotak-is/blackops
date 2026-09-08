# P23-007: AI Development Workflow Efficiency

Status: Accepted — repository workflow implemented and locally verified

## Subsequent Handoff

D2S1 was incomplete when this workflow Task was Accepted. Its subsequent
recovery/Acceptance and current delivery are recorded in the [D2S1 Report](../reports/P23-003D2S1-tenant-scoped-status-source.md).
The following P23-007 execution details retain that historical boundary.

## Goal

ユーザーが求めた開発速度、資料整理、STATEローテーション、エージェント指示とチーム運用を改善する。
Phase23の製品Capability追加ではなく、Repositoryの開発運用を扱う保守Taskとする。

## Authority and Scope

AGENTS、spec109、D158がこのTaskの正本。
既存P23-003D2S1は全件PHPUnitの128MiBメモリ不足で停止し、Reportに再開境界を保存する。
そのProduct/Test10、未完了のAcceptance、既存の検証失敗を変更・隠蔽しない。
全件PHPUnitの修正、リリースCIの省略、公開ドキュメント、依存追加、commit/push/deployは対象外。

Orchestratorが変更するファイル:

- AGENTS.md
- .codex/agents/worker.toml
- .codex/agents/documentation-reviewer.toml
- develop/spec/109-ai-development-workflow.md
- develop/spec/92-documentation-review-agent.md (Profile/Release参照の現行化のみ)
- develop/decisions/158-ai-workflow-efficiency-and-state-rotation.md
- develop/decisions/144-agent-reasoning-profile-upgrade.md (旧値の履歴表示のみ)
- develop/spec/README.md
- develop/TODO.md
- develop/orchestration/README.md
- develop/orchestration/tasks/TEMPLATE.md
- develop/orchestration/reports/TEMPLATE.md
- develop/orchestration/tasks/P23-007-ai-workflow-efficiency.md
- develop/orchestration/reports/P23-007-ai-workflow-efficiency.md
- develop/orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md (停止記録のみ)
- develop/STATE.md
- develop/orchestration/state-archive/**/*.md (STATEの無損失保存)
- develop/orchestration/history/2026-09-08-spec-index.md (旧Index全体の無損失保存)
- develop/orchestration/history/2026-09-08-todo.md (旧TODO全体の無損失保存)

Luna workerの変更範囲:

- develop/orchestration/state.mjs
- develop/orchestration/state.test.mjs
- develop/orchestration/STATE.template.md
- develop/orchestration/state-archive/README.md
- .github/workflows/orchestration.yml

## Team and Ownership

Orchestratorは仕様・管理文書・STATE・統合と最終Reviewを所有する。
GeneralとDocumentationの独立調査はread-onlyで並行し、コードと資料管理の別の疑問を扱う。
Luna/max workerは上記tooling5ファイルを実装し、STATE実データや他Taskを変更しない。
実装の修正後にworkerを停止し、Orchestratorが現在の固定候補のNode unit/CLI検証、実STATE移行・既存差分保全確認を引き継ぐ。
workerから成功ログは未提供のため成功扱いにしない。同一入力の成功ログが得られた場合は監査して再利用する。

## Acceptance Criteria

- STATE更新時に旧内容をbyte単位でarchiveし、現状200行/24KiB以内に置き換えられる。
- 大きすぎる/形式不正/古いSHAを前提にした入力、同時更新、保存失敗で旧STATEを失わない。
- 初回移行で旧STATEの内容を完全保存し、現STATEから履歴と未完了D2S1へ辿れる。
- 新Task/ReportのTemplateに検証選択理由、担当、入力、実行環境、時間、再利用条件とNext Actionがある。
- 通常2名、独立作業があるときだけ最大4名を目安とするチーム契約とファイル所有権を明示する。
- Review修正は影響した確認を再実行し、管理上の文言修正で無関係なlane/全件検証をやり直さない。
- STATE/Task/Report/仕様/Decision/TODO/Indexの責務を整理し、旧Indexは履歴へ移す。
- 独立General Reviewが確認した問題を解消し、未確認事項と上位Goalの残りをReportへ残す。

## Verification Plan

変更分類は開発運用のNode toolingと管理文書。Framework PHP/公開API/SQLは変更しない。
root（workerから引継ぎ）: `node --test --test-isolation=none develop/orchestration/state.test.mjs` と `node --check develop/orchestration/state.mjs`。
この単一Test moduleの各case結果を同じ条件で得るため、RootがCIのCommandにも同じisolation指定を反映する。
root: 初回archiveのSHA一致、`node develop/orchestration/state.mjs --check`、Markdown local links、TOML/YAML解析、`git diff --check`。
AGENTSの既存最低確認であるMago format src/testsとPHP管理番号scanは、D2S1 Finalで成功した入力が不変ならそのログを再利用する。
新しいPHPUnit全件/Mago解析/Deptrac/consumer/exportはこの管理・Node変更では実行しない。
既存D2S1の全件失敗は残件として記録し、Workflow Taskの成功で上書きしない。

## Release Documentation Impact

Stable1.2.0/未Release1.3と公開Capability/route/artifactは変更なし。
開発Repository内のみのToolと指示の変更で公開Source/Search/LLM生成への影響なし。
same-SHA CI、公開Documentation delivery、Production deployは実施しない。
CI追加はSTATE/toolingのread-only検証のみで、既存release品質jobを省略しない。

## Expected Report

`develop/orchestration/reports/P23-007-ai-workflow-efficiency.md`。
変更の理由、検証ログと入力、実測STATE削減量、採用したチーム運用、未完了D2S1、残り工程とNext Actionを記録する。
